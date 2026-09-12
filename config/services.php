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

    /*
    |--------------------------------------------------------------------------
    | IoT Backend Configuration
    |--------------------------------------------------------------------------
    |
    | When deploying to another server, simply change IOT_BACKEND_URL in .env.
    | The dashboard will route all telemetry requests through this endpoint.
    |
    */

    'iot' => [
        'backend_url' => env('IOT_BACKEND_URL', 'https://iot.bppmhkp.online'),
        'local_url'   => env('IOT_LOCAL_URL', 'http://localhost:3000'),
        'api_key'     => env('IOT_API_KEY', ''),
        'timeout'     => (int) env('IOT_TIMEOUT', 5),
    ],

];
