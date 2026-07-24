<?php

$adminPath = '/' . trim((string) (getenv('CONFIG_CENTER_ADMIN_PATH') ?: '/cc-admin'), '/');
if ($adminPath === '/') {
    $adminPath = '/cc-admin';
}

$clientIpWhitelistEnable = getenv('CONFIG_CENTER_CLIENT_IP_WHITELIST_ENABLE');

return [
    'admin_path' => $adminPath,
    'default_namespace' => getenv('CONFIG_CENTER_NAMESPACE') ?: 'public',
    'event_channel' => getenv('CONFIG_CENTER_EVENT_CHANNEL') ?: 'config-center:changed',
    'client_ip_whitelist_enable' => $clientIpWhitelistEnable === false ? true : filter_var($clientIpWhitelistEnable, FILTER_VALIDATE_BOOL),
    'max_content_bytes' => (int) (getenv('CONFIG_CENTER_MAX_CONTENT_BYTES') ?: 524288),
    'outbox_batch_size' => (int) (getenv('CONFIG_CENTER_OUTBOX_BATCH_SIZE') ?: 100),
    'outbox_retry_seconds' => (int) (getenv('CONFIG_CENTER_OUTBOX_RETRY_SECONDS') ?: 5),
];
