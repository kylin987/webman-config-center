ALTER TABLE `cc_config_access_stat`
  DROP INDEX `uk_config_client`,
  ADD UNIQUE KEY `uk_config_client_instance` (`namespace`, `config_group`, `data_id`, `client_account_id`, `client_name`, `last_hostname`);
