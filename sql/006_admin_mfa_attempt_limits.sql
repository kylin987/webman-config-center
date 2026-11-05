-- 已有部署升级时执行一次；新安装也需在 001、002 后执行。
ALTER TABLE `cc_admin_mfa_challenge`
  ADD COLUMN `failed_attempts` smallint unsigned NOT NULL DEFAULT 0;

ALTER TABLE `cc_admin_user`
  ADD COLUMN `mfa_failed_attempts` smallint unsigned NOT NULL DEFAULT 0,
  ADD COLUMN `mfa_failure_window_at` datetime DEFAULT NULL,
  ADD COLUMN `mfa_locked_until` datetime DEFAULT NULL;
