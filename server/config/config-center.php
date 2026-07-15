<?php

return [
    'default_namespace' => getenv('CONFIG_CENTER_NAMESPACE') ?: 'public',
    'event_channel' => getenv('CONFIG_CENTER_EVENT_CHANNEL') ?: 'config-center:changed',
    'max_content_bytes' => (int) (getenv('CONFIG_CENTER_MAX_CONTENT_BYTES') ?: 524288),
    'outbox_batch_size' => (int) (getenv('CONFIG_CENTER_OUTBOX_BATCH_SIZE') ?: 100),
    'outbox_retry_seconds' => (int) (getenv('CONFIG_CENTER_OUTBOX_RETRY_SECONDS') ?: 5),
];

