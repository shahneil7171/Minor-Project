<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Online payment modes for KDP MART
    |--------------------------------------------------------------------------
    |
    | Razorpay and Cashfree are NOT configured for UPI / Card payments in this
    | project, so online payments run in DEMO mode: the payment flow is
    | explicitly simulated and labelled as such, and can produce success,
    | failure and timeout outcomes for testing. No real payment provider is
    | contacted and no real payment is ever "verified" here.
    |
    | Set PAYMENT_DEMO_MODE=false only when a genuine provider is configured
    | and its server-side verification (API or signed webhook) is implemented.
    */
    'demo_mode' => env('PAYMENT_DEMO_MODE', true),

    /*
    |--------------------------------------------------------------------------
    | Payment attempt expiry
    |--------------------------------------------------------------------------
    |
    | A UPI / Card payment attempt expires this many minutes after initiation.
    | Exactly five minutes are required by the product spec; the value is kept
    | in config so it can be changed in one place, but it must not be treated
    | as a "refreshable" deadline — it is set once per attempt.
    */
    'attempt_expiry_minutes' => (int) env('PAYMENT_ATTEMPT_EXPIRY_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Simulated online payment methods
    |--------------------------------------------------------------------------
    |
    | These methods have no real provider integration in this project. COD is
    | handled separately and always works without online verification.
    */
    'simulated_methods' => ['upi', 'card'],

];
