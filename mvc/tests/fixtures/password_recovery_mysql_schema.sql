-- TEST FIXTURE ONLY. Requires separate approval before any database creation/DDL.
-- A human must select a disposable foursmart_recovery_test_* database first.
-- Never execute this fixture against duan1. This file does not create a database.
-- After this fixture, manually apply the separately approved proposed migration.
CREATE TABLE `recovery_test_guard` (
    `marker` VARCHAR(64) NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO `recovery_test_guard` VALUES ('foursmart-password-recovery-isolated-test');

CREATE TABLE `role` (
    `role_id` INT NOT NULL AUTO_INCREMENT,
    `role_name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`role_id`), UNIQUE KEY (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO `role` (`role_id`, `role_name`) VALUES (1, 'admin'), (2, 'user');

CREATE TABLE `account` (
    `tk_id` INT NOT NULL AUTO_INCREMENT,
    `tk_user` VARCHAR(255) NOT NULL,
    `tk_password` VARCHAR(255) NOT NULL,
    `tk_email` VARCHAR(255) NOT NULL,
    `tk_address` TEXT NULL,
    `id_role` INT NULL,
    PRIMARY KEY (`tk_id`), KEY `role_id` (`id_role`),
    CONSTRAINT `fk_recovery_fixture_role` FOREIGN KEY (`id_role`) REFERENCES `role` (`role_id`)
) ENGINE=InnoDB ROW_FORMAT=DYNAMIC DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
