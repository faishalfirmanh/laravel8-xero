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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'xero' => [
        'webhook_key' => env('WEB_HOOK_XERO'),
    ],

    'mekari' => [
        'base_url' => env('MEKARI_API_BASE_URL', 'https://api.mekari.com'),
        'client_id' => env('MEKARI_API_CLIENT_ID'),
        'client_secret' => env('MEKARI_API_CLIENT_SECRET'),
        'channel_integration_id' => env('MEKARI_WA_CHANNEL_ID'),
        'va_template_id' => env('MEKARI_VA_TEMPLATE_ID'),
    ],

    'halal' => [
        'base_url' => env('HALAL_BASE_URL', 'https://testing.duft.co.id/api/mitra/v2'),
        'base_url_v2' => env('HALAL_BASE_URL_V2', 'https://testing.duft.co.id/api-v1/Auth'),
        'client_id' => env('client_id_halal'),
        'client_key' => env('client_key_halal'),
        'basic_user' => env('HALAL_BASIC_USER'),
        'basic_pass' => env('HALAL_BASIC_PASS'),
        'ca_bundle' => env('HALAL_CA_BUNDLE')
    ],
];
