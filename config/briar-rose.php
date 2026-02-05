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
    | Working example:
    | https://0000000.restlets.api.netsuite.com/app/site/hosting/restlet.nl?script=1850&deploy=1
    */
    // Optional Override
    'restlet_base_url' => env('NETSUITE_RESTLET_BASE_URL'),

    // If you want a default RESTlet (optional)
    'restlet_script_id' => env('NETSUITE_RESTLET_SCRIPT_ID'),
    'restlet_deploy_id' => env('NETSUITE_RESTLET_DEPLOY_ID', 1),

    // Environment affects hostname patterns (production, or sandbox)
    'environment' => env('NETSUITE_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | SuiteTalk REST (future expansion)
    |--------------------------------------------------------------------------
    | @todo Add helpers for:
    | - REST Record service
    | - SuiteQL query service
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