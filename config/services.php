<?php

return [

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

    'razorpay' => [
        'enabled'             => env('RAZORPAY_ENABLED', false),
        'mode'                => env('RAZORPAY_MODE', 'test'),
        'key_id'              => env('RAZORPAY_KEY_ID', ''),
        'key_secret'          => env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret'      => env('RAZORPAY_WEBHOOK_SECRET', ''),
        'timeout_seconds'     => (int) env('RAZORPAY_TIMEOUT_SECONDS', 15),
        'require_test_prefix' => env('RAZORPAY_MODE', 'test') === 'test',
    ],

];
