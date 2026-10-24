-- FourSmart public DEMO snapshot, MariaDB 10.4.32 / InnoDB / utf8mb4.

-- Import ONLY into a new empty database selected by the operator.

-- 14 tables, 13 FKs, 8 CHECKs; recovery schema is already included.

-- Do not apply the recovery migration again. No DROP/USE/database/user provisioning.

-- Only synthetic rows below. Public demo credentials are intentional:

-- demo_admin / Demo-Admin!2026 ; demo_client / Demo-Client!2026

-- Keep recovery tables empty. Never use these accounts/passwords on the Internet.

SET NAMES utf8mb4;

SET time_zone = '+00:00';

SET SESSION foreign_key_checks = 1;

SET SESSION check_constraint_checks = 1;



CREATE TABLE `category` (
  `dm_id` int(11) NOT NULL AUTO_INCREMENT,
  `dm_name` varchar(255) NOT NULL,
  PRIMARY KEY (`dm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `password_reset_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `status` varchar(10) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending',
  `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime(6) NOT NULL,
  `available_at` datetime(6) NOT NULL,
  `lock_token` binary(16) DEFAULT NULL,
  `locked_until` datetime(6) DEFAULT NULL,
  `finished_at` datetime(6) DEFAULT NULL,
  `last_error_code` varchar(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_job_ready` (`status`,`available_at`,`id`),
  KEY `idx_password_reset_job_lease` (`status`,`locked_until`,`id`),
  KEY `idx_password_reset_job_email` (`email`,`status`,`id`),
  KEY `idx_password_reset_job_retention` (`created_at`),
  CONSTRAINT `chk_password_reset_job_status` CHECK (`status` in ('pending','processing','sent','discarded','failed')),
  CONSTRAINT `chk_password_reset_job_attempts` CHECK (`attempts` <= 3),
  CONSTRAINT `chk_password_reset_job_lease` CHECK (`status` = 'processing' and `lock_token` is not null and `locked_until` is not null or `status` <> 'processing' and `lock_token` is null and `locked_until` is null),
  CONSTRAINT `chk_password_reset_job_finished` CHECK (`status` in ('sent','discarded','failed') and `finished_at` is not null or `status` in ('pending','processing') and `finished_at` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE `password_reset_rate_limits` (
  `scope` varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `subject_hash` binary(32) NOT NULL,
  `window_started_at` datetime(6) NOT NULL,
  `window_ends_at` datetime(6) NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL DEFAULT 0,
  `last_allowed_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`scope`,`subject_hash`),
  KEY `idx_password_reset_rate_expiry` (`window_ends_at`),
  CONSTRAINT `chk_password_reset_rate_scope` CHECK (`scope` in ('request_email','request_ip','reset_ip')),
  CONSTRAINT `chk_password_reset_rate_window` CHECK (`window_ends_at` = `window_started_at` + interval 15 minute)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE `role` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) NOT NULL,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `account` (
  `tk_id` int(11) NOT NULL AUTO_INCREMENT,
  `tk_user` varchar(255) NOT NULL,
  `tk_password` varchar(255) NOT NULL,
  `tk_email` varchar(255) NOT NULL,
  `tk_address` text DEFAULT NULL,
  `id_role` int(11) DEFAULT NULL,
  `session_version` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`tk_id`),
  KEY `role_id` (`id_role`),
  KEY `idx_account_recovery_email` (`tk_email`,`id_role`),
  CONSTRAINT `account_ibfk_1` FOREIGN KEY (`id_role`) REFERENCES `role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `product` (
  `sp_id` int(11) NOT NULL AUTO_INCREMENT,
  `sp_name` varchar(255) NOT NULL,
  `sp_image` varchar(255) DEFAULT NULL,
  `sp_price` decimal(12,2) NOT NULL,
  `sp_title` varchar(1000) DEFAULT 'Không phí chuyển đổi khi trả góp 0% qua thẻ tín dụng kỳ hạn 3-6 tháng',
  `sp_quantity` int(11) NOT NULL,
  `sp_describe` text DEFAULT NULL,
  `id_dm` int(11) DEFAULT NULL,
  `sp_ma` varchar(255) DEFAULT NULL,
  `sp_pricedel` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`sp_id`),
  KEY `dm_id` (`id_dm`),
  CONSTRAINT `product_ibfk_1` FOREIGN KEY (`id_dm`) REFERENCES `category` (`dm_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `cart` (
  `gh_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tk` int(11) DEFAULT NULL,
  PRIMARY KEY (`gh_id`),
  KEY `tk_id` (`id_tk`),
  CONSTRAINT `cart_ibfk_1` FOREIGN KEY (`id_tk`) REFERENCES `account` (`tk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `comment` (
  `bl_id` int(11) NOT NULL AUTO_INCREMENT,
  `bl_content` text NOT NULL,
  `bl_cmtdate` timestamp NULL DEFAULT current_timestamp(),
  `id_tk` int(11) DEFAULT NULL,
  `id_sp` int(11) DEFAULT NULL,
  PRIMARY KEY (`bl_id`),
  KEY `tk_id` (`id_tk`),
  KEY `comment_ibfk_2` (`id_sp`),
  CONSTRAINT `comment_ibfk_1` FOREIGN KEY (`id_tk`) REFERENCES `account` (`tk_id`),
  CONSTRAINT `comment_ibfk_2` FOREIGN KEY (`id_sp`) REFERENCES `product` (`sp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `order` (
  `dh_id` int(11) NOT NULL AUTO_INCREMENT,
  `dh_orderdate` timestamp NULL DEFAULT current_timestamp(),
  `dh_status` varchar(255) DEFAULT NULL,
  `dh_totalamount` decimal(12,2) NOT NULL,
  `dh_addressUser` text DEFAULT NULL,
  `id_tk` int(11) DEFAULT NULL,
  `dh_nameUser` varchar(255) NOT NULL,
  `dh_note` text DEFAULT NULL,
  `dh_emailUser` varchar(255) NOT NULL,
  `dh_phoneUser` int(11) NOT NULL,
  `dh_countryPay` varchar(255) NOT NULL,
  `dh_cityPay` varchar(255) NOT NULL,
  `dh_districtPay` varchar(255) NOT NULL,
  `dh_communePay` varchar(255) NOT NULL,
  `dh_messagePay` varchar(255) NOT NULL,
  `sp_quantity` varchar(255) NOT NULL,
  `dh_ma` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`dh_id`),
  KEY `tk_id` (`id_tk`),
  CONSTRAINT `order_ibfk_1` FOREIGN KEY (`id_tk`) REFERENCES `account` (`tk_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `password_reset_tokens` (
  `account_id` int(11) NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `password_fingerprint` binary(32) NOT NULL,
  `email_fingerprint` binary(32) NOT NULL,
  `issued_session_version` int(10) unsigned NOT NULL,
  `created_at` datetime(6) NOT NULL,
  `expires_at` datetime(6) NOT NULL,
  `used_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `uq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_token_expiry` (`expires_at`),
  CONSTRAINT `fk_password_reset_token_account` FOREIGN KEY (`account_id`) REFERENCES `account` (`tk_id`) ON DELETE CASCADE,
  CONSTRAINT `chk_password_reset_token_ttl` CHECK (`expires_at` = `created_at` + interval 15 minute),
  CONSTRAINT `chk_password_reset_token_used_at` CHECK (`used_at` is null or `used_at` >= `created_at` and `used_at` < `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;


CREATE TABLE `productcolor` (
  `pc_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sp` int(11) NOT NULL,
  `pc_name` varchar(255) NOT NULL,
  PRIMARY KEY (`pc_id`),
  KEY `fk_productcolor_product` (`id_sp`),
  CONSTRAINT `fk_productcolor_product` FOREIGN KEY (`id_sp`) REFERENCES `product` (`sp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `productmemory` (
  `pm_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_sp` int(11) NOT NULL,
  `pm_name` varchar(50) NOT NULL,
  PRIMARY KEY (`pm_id`),
  KEY `fk_productmemory_product` (`id_sp`),
  CONSTRAINT `fk_productmemory_product` FOREIGN KEY (`id_sp`) REFERENCES `product` (`sp_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `cartdetail` (
  `cd_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_gh` int(11) NOT NULL,
  `id_sp` int(11) NOT NULL,
  `cd_totalamount` int(11) NOT NULL DEFAULT 0,
  `cd_quantity` int(11) NOT NULL DEFAULT 1,
  `cd_option` varchar(255) DEFAULT NULL,
  `cd_optionColor` varchar(255) NOT NULL,
  PRIMARY KEY (`cd_id`),
  KEY `id_gh` (`id_gh`),
  KEY `id_sp` (`id_sp`),
  CONSTRAINT `cartDetail_ibfk_1` FOREIGN KEY (`id_gh`) REFERENCES `cart` (`gh_id`) ON DELETE CASCADE,
  CONSTRAINT `cartDetail_ibfk_2` FOREIGN KEY (`id_sp`) REFERENCES `product` (`sp_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE `orderdetail` (
  `ct_id` int(11) NOT NULL AUTO_INCREMENT,
  `id_dh` int(11) DEFAULT NULL,
  `id_sp` int(11) DEFAULT NULL,
  `ct_quantity` int(11) NOT NULL,
  `od_option` varchar(255) NOT NULL,
  `od_optionColor` varchar(255) NOT NULL,
  PRIMARY KEY (`ct_id`),
  KEY `dh_id` (`id_dh`),
  KEY `sp_id` (`id_sp`),
  CONSTRAINT `orderdetail_ibfk_1` FOREIGN KEY (`id_dh`) REFERENCES `order` (`dh_id`),
  CONSTRAINT `orderdetail_ibfk_2` FOREIGN KEY (`id_sp`) REFERENCES `product` (`sp_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


START TRANSACTION;

INSERT INTO `role` (`role_id`,`role_name`) VALUES
(1,'admin'),
(2,'user');

INSERT INTO `account` (`tk_id`,`tk_user`,`tk_password`,`tk_email`,`tk_address`,`id_role`,`session_version`) VALUES
(1,'demo_admin','$2y$10$F7vfb9mFQ0yiVznF2W7sLehFBMw9ZdxQyhcKaRRK6w6aNdCyR0ffe','admin.demo@example.test','Địa chỉ quản trị minh họa',1,0),
(2,'demo_client','$2y$10$M/JYGnxu2T7bbCQnWekOQurvYYgr5GMQBp1Gy9Qqr1LxyDoc/2BBW','client.demo@example.test','Địa chỉ khách hàng minh họa',2,0);

INSERT INTO `category` (`dm_id`,`dm_name`) VALUES
(1,'Điện thoại demo'),
(2,'Phụ kiện demo');

INSERT INTO `product` (`sp_id`,`sp_name`,`sp_image`,`sp_price`,`sp_title`,`sp_quantity`,`sp_describe`,`id_dm`,`sp_ma`,`sp_pricedel`) VALUES
(1,'FourSmart Demo A','',1200000,'Sản phẩm minh họa A',10,'Dữ liệu giả phục vụ cài đặt local.',1,'DEMO-001',1500000),
(2,'FourSmart Demo B','',500000,'Sản phẩm minh họa B',20,'Không phải sản phẩm hoặc giao dịch thật.',2,'DEMO-002',650000);

INSERT INTO `productcolor` (`pc_id`,`id_sp`,`pc_name`) VALUES
(1,1,'Đen'),
(2,2,'Trắng');

INSERT INTO `productmemory` (`pm_id`,`id_sp`,`pm_name`) VALUES
(1,1,'128GB'),
(2,2,'64GB');

INSERT INTO `cart` (`gh_id`,`id_tk`) VALUES
(1,2);

INSERT INTO `cartdetail` (`cd_id`,`id_gh`,`id_sp`,`cd_totalamount`,`cd_quantity`,`cd_option`,`cd_optionColor`) VALUES
(1,1,1,1200000,1,'128GB','Đen');

INSERT INTO `order` (`dh_id`,`dh_orderdate`,`dh_status`,`dh_totalamount`,`dh_addressUser`,`id_tk`,`dh_nameUser`,`dh_note`,`dh_emailUser`,`dh_phoneUser`,`dh_countryPay`,`dh_cityPay`,`dh_districtPay`,`dh_communePay`,`dh_messagePay`,`sp_quantity`,`dh_ma`) VALUES
(1,'2026-01-01 12:00:00','Chờ xác nhận',1200000,'Địa chỉ giao hàng minh họa',2,'Khách hàng demo','Đơn giả, không giao hàng','client.demo@example.test',0,'Việt Nam','Thành phố demo','Quận demo','Phường demo','Không thanh toán thật','1','DEMO-ORDER-001');

INSERT INTO `orderdetail` (`ct_id`,`id_dh`,`id_sp`,`ct_quantity`,`od_option`,`od_optionColor`) VALUES
(1,1,1,1,'128GB','Đen');

INSERT INTO `comment` (`bl_id`,`bl_content`,`bl_cmtdate`,`id_tk`,`id_sp`) VALUES
(1,'Bình luận minh họa, không phải đánh giá thật.','2026-01-01 12:30:00',2,1);

COMMIT;

-- password_reset_tokens, password_reset_jobs and password_reset_rate_limits remain empty.