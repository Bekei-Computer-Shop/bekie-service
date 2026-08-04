<?php

return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),

    'expiration' => null,

    'middleware' => [
        'verify_csrf_token' => [
            App\Http\Middleware\VerifyCsrfToken::class,
        ],

        'encrypt_cookies' => [
            App\Http\Middleware\EncryptCookies::class,
        ],
    ],
];
