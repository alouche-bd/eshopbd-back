<?php

return [
    'middleware'   =>  [
        'base_uri'  =>  env('MIDDLEWARE_SERVICE_BASE_URL'),
        'secret'  =>  env('MIDDLE_SERVICE_SECRET'),
    ],
    'sso'   =>  [
        'base_uri'  =>  env('URL_SSO'),
        'broker_uuid'  =>  env('BROKER_UUID'),
        'broker_secret'  =>  env('BROKER_SECRET'),
    ],
    's4a'   =>  [
        'base_uri'  =>  env('URL_S4A'),
        'app_uuid'  =>  env('S4A_UUID'),
    ],
    'oauth' => [
        'base_oauth' => env('OAUTH_URI'),
        'id_oauth' => env('S4A_CLIENT'),
        'secret_oauth' => env('S4A_SECRET'),
    ],

    // Mailgun transport — replaces basic SMTP auth (disabled by Microsoft 365
    // for the noreply@biotech-dental.com mailbox). Set MAIL_DRIVER=mailgun
    // in .env to switch over.
    'mailgun' => [
        'secret'   => env('MAILGUN_SECRET'),
        'domain'   => env('MAILGUN_DOMAIN'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        // EU region: api.eu.mailgun.net (your existing config). US region:
        // api.mailgun.net (default).
        'scheme'   => 'https',
    ],

];
