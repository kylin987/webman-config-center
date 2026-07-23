<?php
return  [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver'      => 'mysql',
            'host'        => getenv('MYSQL_HOST') ?: '127.0.0.1',
            'port'        => getenv('MYSQL_PORT') ?: '3306',
            'database'    => getenv('MYSQL_DATABASE') ?: 'config_center',
            'username'    => getenv('MYSQL_USERNAME') ?: 'config_center',
            'password'    => getenv('MYSQL_PASSWORD') ?: '',
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_general_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool' => [
                'max_connections' => 5,
                'min_connections' => 1,
                'wait_timeout' => 3,
                'idle_timeout' => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];
