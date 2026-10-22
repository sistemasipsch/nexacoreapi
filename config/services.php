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

    'kubapp' => [
        'url' => env('KUBAPP_API_URL', 'http://190.145.135.122:8090'),
        'timeout' => env('KUBAPP_TIMEOUT', 10),
    ],

    'convertidor' => [
        'url' => env('MICROSERVICIO_CONVERTIDOR_URL', ''),
        'api_key' => env('MICROSERVICIO_CONVERTIDOR_API_KEY'),
        'alert_email' => env('MICROSERVICIO_ALERT_EMAIL', ''),
    ],

    'monitoreo' => [
        'alert_emails' => env('MONITOR_ALERT_EMAILS', 'sistemasipsch2@gmail.com,sistemas@clinicalhouse.co,nicoleperez0958@gmail.com,junior.arias02yt@gmail.com'),
        'ip_publica' => env('MONITOR_HOST_IP_PUBLICA', '190.145.135.122'),
        'pc_vacunacion' => env('MONITOR_HOST_PC_VACUNACION', ''),
    ],

];
