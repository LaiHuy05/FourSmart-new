-- PROPOSAL ONLY. Do not execute before explicit approval.
-- Inspected server: MariaDB 10.4.32; schema: duan1; account: InnoDB/Dynamic.
-- account.tk_id is a SIGNED INT. Keep the foreign-key type identical.
-- Select the intended database externally; never hard-code duan1 here.
-- This is a one-time UP migration. Existing names must be absent beforehand.
-- DDL implicitly commits in MariaDB: a surrounding transaction is NOT rollback.
-- Schema additions only; no INSERT/UPDATE/DELETE against existing application rows.
-- All DATETIME(6) values are explicit UTC values supplied by the application.

ALTER TABLE `account`
    ADD COLUMN `session_version` INT UNSIGNED NOT NULL DEFAULT 0,
    ADD INDEX `idx_account_recovery_email` (`tk_email`, `id_role`);

-- One current token per account. A newly issued token replaces the old row.
-- token = bin2hex(random_bytes(32)); token_hash = SHA-256(token), raw 32 bytes.
-- Never store the raw token, reset URL or SMTP body in an application table.
-- Email URL uses localhost and #token=...; the fragment stays out of access logs.
CREATE TABLE `password_reset_tokens` (
    `account_id` INT NOT NULL,
    `token_hash` BINARY(32) NOT NULL,
    `password_fingerprint` BINARY(32) NOT NULL,
    `email_fingerprint` BINARY(32) NOT NULL,
    `issued_session_version` INT UNSIGNED NOT NULL,
    `created_at` DATETIME(6) NOT NULL,
    `expires_at` DATETIME(6) NOT NULL,
    `used_at` DATETIME(6) NULL,
    PRIMARY KEY (`account_id`),
    UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
    KEY `idx_password_reset_token_expiry` (`expires_at`),
    CONSTRAINT `fk_password_reset_token_account`
        FOREIGN KEY (`account_id`) REFERENCES `account` (`tk_id`)
        ON DELETE CASCADE ON UPDATE RESTRICT,
    CONSTRAINT `chk_password_reset_token_ttl`
        CHECK (`expires_at` = DATE_ADD(`created_at`, INTERVAL 15 MINUTE)),
    CONSTRAINT `chk_password_reset_token_used_at`
        CHECK (`used_at` IS NULL OR
            (`used_at` >= `created_at` AND `used_at` < `expires_at`))
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Shared across PHP sessions and worker processes, including unknown emails.
-- subject_hash = HMAC-SHA-256(scope + ':' + canonical subject, private key).
-- Never use an account FK here: that would exclude nonexistent addresses.
CREATE TABLE `password_reset_rate_limits` (
    `scope` VARCHAR(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `subject_hash` BINARY(32) NOT NULL,
    `window_started_at` DATETIME(6) NOT NULL,
    `window_ends_at` DATETIME(6) NOT NULL,
    `attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `last_allowed_at` DATETIME(6) NULL,
    PRIMARY KEY (`scope`, `subject_hash`),
    KEY `idx_password_reset_rate_expiry` (`window_ends_at`),
    CONSTRAINT `chk_password_reset_rate_scope`
        CHECK (`scope` IN ('request_email', 'request_ip', 'reset_ip')),
    CONSTRAINT `chk_password_reset_rate_window`
        CHECK (`window_ends_at` = DATE_ADD(`window_started_at`, INTERVAL 15 MINUTE))
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Queue every syntactically valid, rate-allowed email BEFORE account lookup.
-- The worker resolves the address to exactly one account with id_role = 2.
-- This table deliberately has no account FK: unknown addresses also get jobs.
-- email is normalized routing data, not a token; purge jobs within 24 hours.
CREATE TABLE `password_reset_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `status` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_bin
        NOT NULL DEFAULT 'pending',
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME(6) NOT NULL,
    `available_at` DATETIME(6) NOT NULL,
    `lock_token` BINARY(16) NULL,
    `locked_until` DATETIME(6) NULL,
    `finished_at` DATETIME(6) NULL,
    `last_error_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
    PRIMARY KEY (`id`),
    KEY `idx_password_reset_job_ready` (`status`, `available_at`, `id`),
    KEY `idx_password_reset_job_lease` (`status`, `locked_until`, `id`),
    KEY `idx_password_reset_job_email` (`email`, `status`, `id`),
    KEY `idx_password_reset_job_retention` (`created_at`),
    CONSTRAINT `chk_password_reset_job_status`
        CHECK (`status` IN ('pending', 'processing', 'sent', 'discarded', 'failed')),
    CONSTRAINT `chk_password_reset_job_attempts`
        CHECK (`attempts` <= 3),
    CONSTRAINT `chk_password_reset_job_lease`
        CHECK (
            (`status` = 'processing' AND `lock_token` IS NOT NULL
                AND `locked_until` IS NOT NULL)
            OR
            (`status` <> 'processing' AND `lock_token` IS NULL
                AND `locked_until` IS NULL)
        ),
    CONSTRAINT `chk_password_reset_job_finished`
        CHECK (
            (`status` IN ('sent', 'discarded', 'failed') AND `finished_at` IS NOT NULL)
            OR
            (`status` IN ('pending', 'processing') AND `finished_at` IS NULL)
        )
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC
  DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Required application behavior AFTER migration/backend approval:
-- 1. Request limits: 3/email/15 min with a 60-second cooldown; 20/IP/15 min.
--    Token validation limits: 30/IP/15 min (GET and POST combined).
--    Lock rate rows in a consistent order; decide limits and enqueue atomically.
--    Retain last_allowed_at across window rollover; saturate counters at limits.
--    Use REMOTE_ADDR, never untrusted forwarding headers, for the IP subject.
-- 2. Canonical email = strtolower(trim(email)); reject malformed/oversized input.
--    Match using the existing account collation; discard ambiguous matches.
--    Response status/message must not depend on account existence or SMTP state.
-- 3. Worker: short claim transaction, 120-second lease, at most 3 attempts.
--    Never start/resume jobs older than 5 minutes; retry only within that window.
--    Use SELECT ... FOR UPDATE / compare-and-swap; no SKIP LOCKED dependency.
--    Recheck job status/lease under the account lock before issuing a token.
--    Commit token hash before SMTP; never hold a DB transaction during SMTP.
--    Retry generates a fresh token, replacing the previous token for the account.
--    Mark completion only if lock_token still matches; store allowlisted error codes.
--    A crash after SMTP may cause another email; delivery is not exactly-once.
-- 4. Fingerprints: SHA-256(stored tk_password) and SHA-256(canonical tk_email),
--    each as raw 32 bytes. Check them and issued_session_version at reset time.
--    Fingerprint mismatches invalidate tokens. Authenticated password changes also
--    increment session_version and delete current tokens/cancel queued jobs.
-- 5. Reset transaction: lock account, token and related jobs consistently; require
--    id_role = 2, matching fingerprints/version, used_at IS NULL, expires_at > now.
--    Update tk_password using password_hash(..., PASSWORD_BCRYPT), increment
--    session_version, conditionally set used_at, and discard pending/processing
--    jobs for that email. Verify affected rows and commit all changes together.
--    The account lock also serializes token issuance against password reset.
--    GET must not consume tokens; only a valid, CSRF-protected POST may consume.
-- 6. Login records session_version. Before authenticated request dispatch, compare
--    it to the database; mismatch/missing value invalidates the PHP login session.
--    Existing pre-feature sessions must log in again. No PHP session-table needed.
--    A request already executing is not retroactively interrupted by revocation.
-- 7. Worker housekeeping: expire pending jobs at 5 minutes; purge all jobs older
--    than 24 hours, expired tokens after 24 hours, and rate windows ended >24h ago.
--    Do not use MySQL events/triggers. Recovery/versioning remain disabled until
--    FOURSMART_PASSWORD_RECOVERY_ENABLED=1 after separately approved migration.
--
-- Deployment/rollback notes (not executable DOWN statements):
-- Back up schema; validate UP on a disposable test database before real use.
-- Keep foreign_key_checks/check_constraint_checks enabled. Use explicit lengths
-- in PHP: the inspected server SQL mode does not currently include strict mode.
-- On partial DDL failure, inspect actual schema before any retry; do not use --force.
-- Disable recovery routes/worker before removing the 3 new tables and account
-- index/column. Preserve the increased session versions or invalidate all login
-- sessions before a later redeployment, so rollback cannot revive stale sessions.
