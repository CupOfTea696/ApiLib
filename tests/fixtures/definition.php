<?php

return [
    'base' => 'https://reqres.in/api/',
    'endpoints' => [
        'colors' => [
            'colors' => ['index', 'store'],
            'colors/{colorId}' => ['show', 'update', 'delete'],
        ],
        'users' => [
            'users' => ['index'],
            'users/{userId}' => ['show'],
        ],
    ],
    'query' => [],
    'global_query' => [
        'page' => ['index'],
        'per_page' => ['index'],
        'delay' => [
            'index',
            'show',
            'store',
            'update',
            'delete',
        ],
    ],
];
