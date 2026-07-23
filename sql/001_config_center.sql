CREATE TABLE `cc_admin_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cc_config_item` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `namespace` varchar(64) NOT NULL DEFAULT 'public',
  `config_group` varchar(128) NOT NULL,
  `data_id` varchar(255) NOT NULL,
  `format` varchar(16) NOT NULL,
  `content` mediumtext NOT NULL,
  `content_md5` char(32) NOT NULL,
  `revision` bigint unsigned NOT NULL,
  `updated_by` varchar(64) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_identity` (`namespace`, `config_group`, `data_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cc_config_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `config_item_id` bigint unsigned NOT NULL,
  `namespace` varchar(64) NOT NULL,
  `config_group` varchar(128) NOT NULL,
  `data_id` varchar(255) NOT NULL,
  `format` varchar(16) NOT NULL,
  `content` mediumtext NOT NULL,
  `content_md5` char(32) NOT NULL,
  `revision` bigint unsigned NOT NULL,
  `operator` varchar(64) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_config_revision` (`config_item_id`, `revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cc_config_outbox` (
  `id` char(36) NOT NULL,
  `event_type` varchar(32) NOT NULL,
  `payload` json NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `available_at` datetime NOT NULL,
  `published_at` datetime DEFAULT NULL,
  `last_error` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pending` (`published_at`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cc_client_account` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `username` varchar(64) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_client_username` (`username`),
  UNIQUE KEY `uk_client_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
