<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Razorpay API credentials
    |--------------------------------------------------------------------------
    |
    | These credentials are obtained from the Razorpay Dashboard.
    | KEY_ID is the public key (safe to expose to the frontend).
    | KEY_SECRET is the secret key (must NEVER be exposed to the frontend
    | or committed to version control).
    */
    'key_id'     => env('RAZORPAY_KEY_ID', ''),
    'key_secret' => env('RAZORPAY_KEY_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Razorpay webhook secret
    |--------------------------------------------------------------------------
    |
    | Used to verify the signature of incoming webhook callbacks.
    | Set this in your Razorpay Dashboard webhook settings.
    */
    'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Razorpay operating mode
    |--------------------------------------------------------------------------
    |
    | 'test'  -> Use Razorpay test credentials (default, safe for development)
    | 'live'  -> Use Razorpay live credentials (real money, merchant activation required)
    |
    | Never set this to 'live' unless you have verified credentials, merchant
    | activation, and have explicitly decided to go live.
    */
    'mode' => env('RAZORPAY_MODE', 'test'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | KDP MART operates in INR. Razorpay amounts are passed in the smallest
    | currency unit (paise for INR).
    */
    'currency' => 'INR',

];