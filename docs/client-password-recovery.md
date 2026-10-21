# FourSmart: Client password recovery

Implementation branch: `feature/client-password-recovery`.

**Migration has not been applied to `duan1`.** The feature defaults to OFF.
The user separately approved `duan1_test`; the unchanged migration was applied
there and MariaDB tests passed. See the current
[MariaDB verification and Chrome guide](client-password-recovery-mariadb-verification.md).
Application code never creates databases or applies migrations. Explicit test
provisioning is isolated in `mvc/tests/setup_duan1_test.php`.
SQLite tests build independent, disposable fixtures; they never execute the
proposed MariaDB migration and never use the `duan1` connection.

## Architecture

- Routes: `?client=forgotPassword` and `?client=resetPassword`.
- `PasswordRecoveryConfig` reads process environment only. No `.env` loader,
  public key file, new Composer dependency, Redis or RabbitMQ is needed.
- Controller handles HTTP, CSRF, generic responses and a restricted reset context.
- Service validates inputs and coordinates request, reset and delivery.
- Model owns prepared SQL, transactions, rate limits, token consumption and jobs.
- CLI worker claims jobs with a 120-second lease and random ownership token.
  Concurrent processes use row locks and ownership checks. MariaDB deadlocks and
  lock timeouts retry the database transaction at most twice after rollback.
- Mailer uses PHPMailer, SMTP `127.0.0.1:1025`, no authentication, no TLS, no
  automatic STARTTLS, no SMTP debug output and no alternative SMTP hosts.
- New pages reuse `base.css`/`account.css` and the existing FourSmart auth layout.
  Admin controllers/views and existing CSS are unchanged.
- `SessionVersionPolicy` is independent of the recovery switch. See the
  [session policy deployment and regression guide](session-version-policy.md).

## Security and behavior

The request endpoint does not query `account`. Valid email submissions perform
the same email/IP rate-limit and queue operations, then return the same 303
redirect and generic message whether the account exists, is an Admin, or is
rate-limited. A worker resolves the address to exactly one Client (`id_role=2`).
Ambiguous addresses are discarded; the existing email column is not made UNIQUE.

- Token: `bin2hex(random_bytes(32))`, with only SHA-256 binary digest in the DB.
- TTL: 15 minutes from issuance, checked again under reset locks including time
  spent hashing the password, waiting for locks and retrying transactions.
- One current token/account. Replacement invalidates its predecessor.
- Reset is a CSRF-protected POST. GET does not consume a token. Password, token
  consumption, session version and job cancellation commit together.
- Passwords use bcrypt and the phase 1 policy: at least 12 Unicode characters,
  no more than 72 UTF-8 bytes, uppercase/lowercase ASCII letters, digit, symbol.
  Passwords are not trimmed. Reusing the current password is rejected.
- Email links use `http://localhost/.../index.php?client=resetPassword#token=...`.
  The fragment is not sent to Apache or in Referer. Page JavaScript moves it to a
  hidden POST field and removes it from browser history. JavaScript is required
  on the reset page. Tokens are not stored in localStorage/sessionStorage.
- On a validation error the server retains only a digest in the PHP session;
  submitted tokens/passwords are never rendered back into HTML. The digest is
  revalidated against the database and does not grant login privileges.
- Pages send `Cache-Control: no-store`, `Referrer-Policy: no-referrer`, CSP with
  self-only scripts/styles/forms, `frame-ancestors 'none'` and `nosniff`.
- Session cookies are host-only, HttpOnly, SameSite=Lax, session-only; Secure is
  enabled on HTTPS and disabled for local HTTP. Strict session IDs are enabled.
- Normal login rotates the session ID and CSRF token. Reset clears login state
  and rotates the ID/CSRF, then requires a normal login.
- After reset, all older sessions fail their version check on the next request.
  A request already executing is not retroactively interrupted.
- Authenticated phase 1 changes also increment the version and cancel old
  recovery work in `required` mode, retaining only the changing device with the
  new version. This remains active when email recovery is OFF.
- Legacy plaintext-to-bcrypt login upgrade keeps its original phase 1 behavior;
  it does not itself increment the session version.

Limits are stored in the DB, not in the browser session: 3 accepted requests per
email/15 minutes with 60-second spacing, 20 requests per IP/15 minutes, and 30
reset-page GET/POST checks per IP/15 minutes. The email cooldown carries over a
window boundary. IP comes from `REMOTE_ADDR`; untrusted forwarded headers are
ignored. Rate keys use domain-separated HMAC-SHA-256 with the private key.

Worker delivery has at most 3 attempts, delayed 10 then 20 seconds. Jobs older
than 5 minutes are discarded. Retry creates a fresh token; a crash after SMTP
acceptance can produce another email. Delivery is not exactly-once; only the
latest issued token can be used. SMTP happens outside transactions. Error codes
are allowlisted, without SMTP payloads, credentials or exception details.
The worker purges jobs older than 24 hours and tokens/rate windows expired more
than 24 hours ago. Run the worker periodically for cleanup even when idle.

## Configure XAMPP after migration approval

Before migration, keep `FOURSMART_SESSION_VERSION_MODE` unset or `legacy` and
`FOURSMART_PASSWORD_RECOVERY_ENABLED` unset or `0`. Legacy mode does not select
or update a `session_version` column. After the separately approved complete
migration, set the session mode to `required` in Apache and workers, initially
keeping recovery OFF. Required mode always checks session versions and increments
the version on password changes, without a HMAC key. Recovery refuses to run under legacy mode.

After **separate approval**, validate the SQL on an approved disposable MariaDB
database before applying it to `duan1`. The proposed SQL targets MariaDB 10.4.32,
InnoDB, signed `account.tk_id INT`, Dynamic row format and enabled CHECK/FK
constraints. It deliberately contains no `USE duan1`, automatic migration, event
or trigger. DDL implicitly commits and is not rolled back by a surrounding
transaction. Inspect partially applied DDL before retrying; never use `--force`.
The inspected server lacks strict SQL mode, so PHP performs explicit lengths.

Generate a private key in the process environment, without printing it:

```powershell
$env:FOURSMART_RECOVERY_HMAC_KEY = & 'C:\xampp\php\php.exe' -r 'echo base64_encode(random_bytes(32));'
```

Use the same stable key for Apache and every worker. Store a persistent copy in
a protected configuration or secret store **outside all web/document roots and
outside Git**, for example an Apache configuration file under
`C:\xampp\apache\conf\extra` with filesystem ACLs limited to the administrator
and Apache/worker identity. Reference that external configuration from Apache.
Do not put the key in a repository `.env`, a public PHP file, Task Scheduler
arguments, terminal transcripts, or documentation. Apache `SetEnv` entries must
be in the private server configuration, not a public `.htaccess`.

The required process variables are:

```text
FOURSMART_SESSION_VERSION_MODE=required
FOURSMART_PASSWORD_RECOVERY_ENABLED=1
FOURSMART_APP_URL=http://localhost/FourSmart/mvc/index.php
FOURSMART_RECOVERY_HMAC_KEY=<base64-encoded private key of at least 32 bytes>
```

The key above is a placeholder. PHP reads `getenv()`; it does not automatically
load `.env`. Apache restart may be needed after changing its environment.
Changing the HMAC key resets rate-limit identities, so do not regenerate it
for each scheduled worker execution. Token hashes do not use this key.

After schema/configuration approval and setup, use only:

```text
http://localhost/FourSmart/mvc/index.php?client=forgotPassword
http://localhost/FourSmart/mvc/index.php?client=login
http://127.0.0.1:8025
```

Application links use `localhost`; the numeric loopback addresses above are
only SMTP/Mailpit endpoints. Do not mix application cookies between localhost
and 127.0.0.1. The URL is never constructed from the HTTP Host header.

Worker commands, from `C:\xampp\htdocs\FourSmart`, **after approval/setup**:

```powershell
php mvc/cli/password_recovery_worker.php --once
php mvc/cli/password_recovery_worker.php
```

`--once` handles one job; the default drains at most 100 eligible jobs and exits.
It does not loop forever or install a scheduled task. For Task Scheduler use
`C:\xampp\php\php.exe`, arguments
`C:\xampp\htdocs\FourSmart\mvc\cli\password_recovery_worker.php`, working
directory `C:\xampp\htdocs\FourSmart`, and an externally configured environment.
Schedule once a minute. Overlapping executions are protected by database leases.
Without valid configuration the worker exits 1 before connecting to MySQL.

## Safe testing before migration

The following tests are allowed without touching MariaDB. Synthetic accounts and
session files live in memory or a guarded temporary directory outside the web
root, and are deleted after execution. The HTTP harness binds to loopback but
uses `localhost` in application URLs. No dependency install is required.

```powershell
php mvc/tests/password_recovery_model_test.php
php mvc/tests/client_password_recovery_test.php --mailpit
node mvc/tests/client_password_recovery_js_test.js
php mvc/tests/client_password_change_test.php
php mvc/tests/session_version_policy_test.php
```

To run all phase 1 tests with versioning enabled (a child-process environment,
not production configuration):

```powershell
$previousSessionMode = $env:FOURSMART_SESSION_VERSION_MODE
try {
    $env:FOURSMART_SESSION_VERSION_MODE = 'required'
    php mvc/tests/client_password_change_test.php
} finally {
    $env:FOURSMART_SESSION_VERSION_MODE = $previousSessionMode
}
```

Mailpit delivery uses the real worker service/model/mailer, with its PDO connection
pointing only to a SQLite fixture. It checks the exact sent token hash via the
Mailpit API. No Mailpit messages are deleted. A normal automated run closes the
temporary server, so its email link is only usable during that run. For manual
browser preview without a real migration:

```powershell
php mvc/tests/client_password_recovery_test.php --mailpit --serve
```

Keep that process open, use the printed `http://localhost:<port>` URL and the
latest email for `customer@example.test` in Mailpit. The fixture uses synthetic
data; the application database remains disconnected. Press Enter to stop and
delete the fixture. The preview runs the automated checks first.

The JS suite simulates DOM behavior using Node built-ins. It does not claim
real-browser visual verification. Existing CSS and Admin source are preserved.

## MariaDB integration: approved duan1_test only

Running this command without its explicit arguments performs no connection or SQL:

```powershell
php mvc/tests/password_recovery_mysql_test.php
```

It reports `NOT TESTED`. The approved test database is now exactly `duan1_test`,
with a dedicated `foursmart_duan1_test@127.0.0.1` identity and private configuration
outside Git and web roots. Every write/transaction checks DATABASE()/CURRENT_USER().
The harness rejects production targets before execution, creates no databases,
and applies no migration. Its fault-injection trigger is removed after testing.
The earlier standalone SQL fixture is retained as a template; it was not used
for the current run, which copied all 11 source table structures without rows.

Use the current report for private-config arguments, evidence and exact Chrome
commands. Do not rerun provisioning against the populated test database. The
application connected to `duan1` remains disabled. Test-only PHP routers inject
the restricted connection and never fall back to the application connection.

## Changed files

Initial implementation verification, before the separate MariaDB approval
(2026-10-21). Current results superseding the MariaDB row are in the linked report:

| Check | Result | Evidence |
| --- | --- | --- |
| PHP syntax | PASS | 83 PHP files; exit 0 |
| JavaScript syntax | PASS | 3 JS files; exit 0 |
| SQLite model/service/config/concurrency | PASS | 21 passed, 0 failed |
| HTTP, sessions, CSRF, Admin smoke, real Mailpit | PASS | 11 passed, 0 failed |
| Fragment/form JavaScript behavior | PASS | 5 passed, 0 failed; simulated DOM |
| Full phase 1 suite, feature OFF | PASS | 41 passed, 0 failed |
| Full phase 1 suite, feature ON | PASS | 41 passed, 0 failed |
| MariaDB integration and migration execution | NOT TESTED | Harness default makes no connection; separate approval required |
| Real-browser visual rendering and HTTPS cookie delivery | NOT TESTED | HTTP fixture and DOM simulation only |

Latest verified Mailpit message ID: `7UIl6Vc4q4jFn1roYRIZLk`.
Its email fragment token was hashed and matched against the SQLite token row;
no raw token or private key is recorded here. The automated fixture is removed
after the run, so that message is evidence rather than a live application link.
Read-only inspection of MariaDB 10.4.32 after testing confirmed zero recovery
tables and zero `session_version` columns in `duan1`. No unresolved test failures
remain in the executed suites. MariaDB DDL/locking behavior is not certified by
these results.

Created:

```text
docs/client-password-recovery.md
mvc/config/password_recovery.php
mvc/core/SessionGuard.php
mvc/model/PasswordRecoveryModel.php
mvc/service/PasswordRecoveryService.php
mvc/service/PasswordRecoveryMailer.php
mvc/controller/client/password_recovery_controller.php
mvc/cli/password_recovery_worker.php
mvc/view/client/login/forgot_password.php
mvc/view/client/login/reset_password.php
mvc/view/assets/js/client-password-recovery.js
mvc/tests/password_recovery_model_test.php
mvc/tests/client_password_recovery_test.php
mvc/tests/client_password_recovery_js_test.js
mvc/tests/password_recovery_mysql_test.php
mvc/tests/support/password_recovery_fixture.php
mvc/tests/support/password_recovery_router.php
mvc/tests/support/password_recovery_race.php
mvc/tests/fixtures/password_recovery_mysql_schema.sql
```

Updated:

```text
mvc/migrations/20261021_client_password_recovery.proposed.sql
mvc/core/bootstrap.php
mvc/index.php
mvc/model/AccountModel.php
mvc/controller/client/client_controller.php
mvc/controller/client/auth_controller.php
mvc/controller/client/profile_controller.php
mvc/view/client/login/login.php
mvc/tests/client_password_change_test.php
```

The phase 1 test fixture now includes `session_version` and recovery tables, and
the success assertion checks its increment. All original 41 cases are retained.
Pre-existing `.gitignore`, Composer files, skill directories and the earlier
Mailpit smoke script are not changed by this implementation.

## Rollback planning

Disable recovery and workers first; keep `FOURSMART_SESSION_VERSION_MODE=required`.
Do not downgrade the session policy to turn off email. Do not drop columns while
versioned sessions are still trusted. Preserve security versions or invalidate all PHP login
sessions before removing schema/redeploying; resetting versions to zero could
revive stale sessions. A schema backup and explicit rollback review are needed.
No rollback or migration is performed by this implementation.
