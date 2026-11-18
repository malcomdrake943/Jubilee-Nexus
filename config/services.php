<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'scraperapi' => [
        'key' => env('SCRAPER_API_KEY', "25e1bef6a0b0cf1dd685068748ce"),
    ],

    'rainforest' => [
        'key' => env('RAINFOREST_API_KEY'),
    ],

    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
    ],

    'cjdropshipping' => [
        'api_key'        => env('CJ_API_KEY'),
        'api_secret'     => env('CJ_API_SECRET'),
        'base_url'       => env('CJ_BASE_URL', 'https://developers.cjdropshipping.com/api2.0/v1'),
        'markup_percent' => (float) env('CJ_DEFAULT_MARKUP_PERCENT', 25),
        'auto_fulfill'   => filter_var(env('CJ_AUTO_FULFILL', true), FILTER_VALIDATE_BOOLEAN),
        'auto_pay'       => filter_var(env('CJ_AUTO_PAY_BALANCE', true), FILTER_VALIDATE_BOOLEAN),
        'webhook_secret' => env('CJ_WEBHOOK_SECRET'),
    ],

];
