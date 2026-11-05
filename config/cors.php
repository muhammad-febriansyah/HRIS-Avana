<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    // `berkas/*` serves the signed private files (avatars, receipts, post
    // photos). The Flutter web app draws images on a canvas, which the browser
    // only allows for responses that carry CORS headers — without them every
    // photo in the web app rendered blank.
    'paths' => ['api/*', 'berkas/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Token-authenticated (no cookies), so any origin is safe to answer; set
    // CORS_ALLOWED_ORIGINS (comma separated) to narrow it.
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // Lets the browser reuse a preflight for a day instead of asking before
    // every API call from the web app.
    'max_age' => 86400,

    'supports_credentials' => false,

];
