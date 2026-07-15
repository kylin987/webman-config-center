<?php

return [
    'endpoint' => getenv('CONFIG_CENTER_ENDPOINT') ?: 'https://config.example.com/',
    'token' => getenv('CONFIG_CENTER_TOKEN') ?: '',
    'namespace' => 'public',
    'config_root' => base_path() . '/config/nacos',
    'state_dir' => runtime_path() . '/config-center',
    'connect_timeout' => 3,
    'timeout' => 8,
    'items' => [
        [
            'group' => 'DEFAULT_GROUP',
            'data_id' => 'app.php',
            'format' => 'php',
            'path' => 'app.php',
        ],
    ],
];

