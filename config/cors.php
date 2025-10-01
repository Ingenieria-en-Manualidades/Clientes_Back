<?php

return [
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie', // si usas Sanctum SPA
        'login',
        'logout',     // si pegas al login/logout del backend
    ],

    'allowed_methods' => ['*'],

    // PON tus orígenes EXACTOS (nada de '*')
    'allowed_origins' => [
        'https://cim.ienm.com.co',
        'https://cimb.ienm.com.co',
        'http://localhost:3000',
        'http://127.0.0.1:3000',
    ],

    'allowed_origins_patterns' => [],

    // Si no quieres pelearte con headers, deja '*' o enumera los que uses
    'allowed_headers' => ['*'],
    'Access-Control-Allow-Headers' => 'Authorization,Content-Type,Accept,Origin,X-Requested-With, X-XSRF-TOKEN, X-CSRF-TOKEN',


    'exposed_headers' => [],

    // Puedes cachear el preflight
    'max_age' => 3600,

    // MUY IMPORTANTE para credentials: true
    'supports_credentials' => true,
];
