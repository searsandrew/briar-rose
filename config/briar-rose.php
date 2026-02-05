<?php

return [
    /*
    |--------------------------------------------------------------------------
    | NetSuite Account / Realm
    |--------------------------------------------------------------------------
    | Example: "5802217" or "5802217_SB1"
    */
    'account' => env('NETSUITE_ACCOUNT'),

    /*
    |--------------------------------------------------------------------------
    | Token-Based Auth (OAuth 1.0a)
    |--------------------------------------------------------------------------
    */
    'consumer_key' => env('NETSUITE_CONSUMER_KEY'),
    'consumer_secret' => env('NETSUITE_CONSUMER_SECRET'),
    'token_id' => env('NETSUITE_TOKEN_ID'),
    'token_secret' => env('NETSUITE_TOKEN_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | RESTlet
    |--------------------------------------------------------------------------
    | Your working example:
    | https://5802217.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1850&deploy=1
    */
    'restlet_base_url' => env('NETSUITE_RESTLET_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | SuiteTalk REST (future expansion)
    |--------------------------------------------------------------------------
    | We'll add helpers for:
    | - REST Record service
    | - SuiteQL query service
    |
    | Typical base patterns are account- and environment-dependent, so we leave
    | this configurable and add builders later.
    */
    'rest_base_url' => env('NETSUITE_REST_BASE_URL'),

    /*
    |--------------------------------------------------------------------------
    | HTTP
    |--------------------------------------------------------------------------
    */
    'timeout' => env('NETSUITE_TIMEOUT', 30),
    'connect_timeout' => env('NETSUITE_CONNECT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Debug logging (redacted)
    |--------------------------------------------------------------------------
    */
    'log_requests' => env('NETSUITE_LOG_REQUESTS', false),
];