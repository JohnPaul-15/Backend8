<?php

return [

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'auth/*',
        'login',
        'register',
        'logout',
        'profile',
        'books/*',
        'transactions/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',  // Next.js development server
        'http://127.0.0.1:3000', // Alternative Next.js development server
        env('FRONTEND_URL', 'http://localhost:3000'), // Production frontend URL
        'http://localhost:3001',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'X-Requested-With',
        'Content-Type',
        'Accept',
        'Authorization',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        '*',
    ],

    'exposed_headers' => [
        'Authorization',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
    ],

    'max_age' => 0,

    'supports_credentials' => true,
];

