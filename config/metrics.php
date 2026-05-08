<?php

// config for motuslogistik/Metrics
use motuslogistik\Metrics\Stores\ArrayStore;

return [
    'store' => ArrayStore::class,
    'global_store' => null,
    'prefix' => 'metrics|',

    'swoole' => [
        'size' => 4096,
        'string_size' => 64,
    ],

    'redis' => [
        'connection' => null,
    ],

    'route' => [
        'enabled' => true,
        'path' => '/metrics',
        'middleware' => [],
    ],
];
