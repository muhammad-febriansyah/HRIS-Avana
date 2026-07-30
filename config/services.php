<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'pakasir' => [
        'base_url' => env('PAKASIR_BASE_URL', 'https://app.pakasir.com'),
        'slug' => env('PAKASIR_SLUG', 'avanahr'),
        'api_key' => env('PAKASIR_API_KEY'),
    ],

    // Speech-to-text for the meeting recorder. The project key belongs here
    // rather than in code: it is read as a fallback when no key has been saved
    // in Pengaturan AI, and it never reaches the phone — the app is handed a
    // short-lived grant minted from it. See App\Services\MeetingTranscriber.
    'deepgram' => [
        'api_key' => env('DEEPGRAM_API_KEY'),
    ],

    'firebase' => [
        // Path to the FCM service-account JSON (Firebase → Project settings →
        // Service accounts → Generate new private key). Defaults to the
        // git-ignored storage path; push is a no-op until the file exists.
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
        'project_id' => env('FIREBASE_PROJECT_ID', 'febrinogen-learn'),
    ],

];
