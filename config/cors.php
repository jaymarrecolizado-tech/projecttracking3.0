<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Same-origin by default: cross-origin API consumers must be listed in
    // CORS_ALLOWED_ORIGINS (comma-separated). Probes use bearer tokens, not
    // browsers, so a closed default breaks nothing legitimate.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
