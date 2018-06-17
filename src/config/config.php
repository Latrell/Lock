<?php
return [

    // null file memcached redis
    'default' => env('LOCK_DRIVER', 'redis'),

    'stores' => [

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/lock')
        ],

        'memcached' => [
            'driver' => 'memcached',
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100
                ]
            ]
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default'
        ]
    ],

    'prefix' => 'lock',

    // 锁超时时间（秒）
    'timeout' => 30,

    // 上锁最大超时时间（秒）
    'max_timeout' => 300,

    // 重试等待时间（微秒）
    'retry_wait_usec' => 100000
];
