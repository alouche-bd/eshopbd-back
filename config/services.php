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
    'galaxy_api' => [
        'enabled' => env('GALAXY_API_ENABLED', false),
        'base_url' => env('GALAXY_API_BASE_URL'),
        'keycloak_url' => env('GALAXY_KEYCLOAK_URL'),
        'keycloak_realm' => env('GALAXY_KEYCLOAK_REALM', 's4a'),
        'keycloak_client_id' => env('GALAXY_KEYCLOAK_CLIENT_ID'),
        'keycloak_client_secret' => env('GALAXY_KEYCLOAK_CLIENT_SECRET'),
        'redirect_uri' => env('GALAXY_LOGIN_REDIRECT_URI'),
        'timeout' => env('GALAXY_API_TIMEOUT', 15),
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
