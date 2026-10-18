<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cashfree API credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are obtained from the Cashfree Merchant Dashboard.
    | KEY_ID is the x-client-id (public identifier).
    | KEY_SECRET is the x-client-secret (must NEVER be exposed to the frontend).
    | Both values must be set before online payments are enabled.
    |
    | Cashfree does not use a public key for checkout. The client ID is the
    | only value that may be treated as public within the HTML you render.
    */

    'key_id'     => env('CASHFREE_KEY_ID', ''),
    'key_secret' => env('CASHFREE_KEY_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Cashfree webhook secret
    |--------------------------------------------------------------------------
    |
    | Used to verify the signature of incoming Cashfree webhook callbacks.
    | Set this in the Cashfree Dashboard webhook settings.
    |
    | Cashfree signs webhooks as:
    |   signature = base64_hmac_sha256( webhook_timestamp . raw_body , x-client-secret )
    */

    'webhook_secret' => env('CASHFREE_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Cashfree operating mode
    |--------------------------------------------------------------------------
    |
    | 'test'  -> sandbox.cashfree.com and Cashfree\Cashfree::SANDBOX (0)
    | 'live'  -> api.cashfree.com and Cashfree\Cashfree::PRODUCTION (1)
    |
    | Test mode is the default and safe for development. Never enable 'live'
    | until you have verified merchant activation and are ready for real money.
    */

    'mode' => env('CASHFREE_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Cashfree API version
    |--------------------------------------------------------------------------
    |
    | All Cashfree Payments APIs are versioned. 2025-01-01 is the current
    | stable version for KDP MART. Change this only if your merchant account
    | supports a newer API version.
    */

    'api_version' => env('CASHFREE_API_VERSION', '2025-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Cashfree endpoint mapping
    |--------------------------------------------------------------------------
    |
    | Internal helper resolves sandbox vs production URLs from the mode above.
    */

    'environment' => env('CASHFREE_ENVIRONMENT', 'sandbox'),

];