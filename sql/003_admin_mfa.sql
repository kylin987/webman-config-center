ALTER TABLE `cc_admin_user`
  ADD COLUMN `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `password_hash`,
  ADD COLUMN `mfa_secret` varchar(64) DEFAULT NULL AFTER `mfa_enabled`,
  ADD COLUMN `mfa_enabled_at` datetime DEFAULT NULL AFTER `mfa_secret`;

CREATE TABLE `cc_admin_mfa_challenge` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `mfa_secret` varchar(64) DEFAULT NULL,
  `setup_required` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_token_hash` (`token_hash`),
  KEY `idx_admin_user_id` (`admin_user_id`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
