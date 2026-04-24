<?php

// config for motuslogistik/Metrics
use motuslogistik\Metrics\Stores\ArrayStore;

return [
    'store'  => ArrayStore::class,
    'prefix' => 'metrics|',

    'swoole' => [
        'size'        => 4096,
        'string_size' => 64,
    ],

    'route' => [
        'enabled'    => true,
        'path'       => '/metrics',
        'middleware' => [],
    ],
];
