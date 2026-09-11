<?php

use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Laravel\Sanctum\Sanctum;

return [

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,localhost:5173,localhost:8000,127.0.0.1,127.0.0.1:8000',
        Sanctum::currentApplicationUrlWithPort()
    ))),

    'guard' => ['web'],

    // Probe-token lifetime in minutes. Null means never expire; the default
    // rotates field tokens every 30 days (see ProbeTokenController).
    'expiration' => env('SANCTUM_EXPIRATION', 43200),

    'token_prefix' => '',

    'middleware' => [
        'verify_csrf_token' => VerifyCsrfToken::class,
        'encrypt_cookies' => EncryptCookies::class,
    ],

];
