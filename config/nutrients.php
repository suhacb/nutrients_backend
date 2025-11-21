<?php

return [
    'name' => env('APP_NAME'),
    'auth' => [
        'url_frontend' => env('AUTH_FRONTEND_URL'),
        'port_frontend' => env('AUTH_FRONTEND_PORT'),
        
        'url_backend' => env('AUTH_BACKEND_URL'),
        'port_backend' => env('AUTH_BACKEND_PORT')
    ],
    'backend' => [
        'url' => env('APP_BACKEND_URL'),
        'port' => env('APP_BACKEND_PORT') ?? null
    ],
    'frontend' => [
        'url' => env('APP_URL'),
        'port' => env('APP_PORT') ?? null
    ],
];