<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Configuration for each payment gateway. Secrets are used for
    | callback signature verification. In production, use environment
    | variables and never hardcode secrets.
    |
    */

    'default' => env('PAYMENT_DEFAULT', 'cod'),

    'jawwal_secret' => env('JAWWAL_PAY_SECRET', 'test-secret'),

    'palpay_secret' => env('PALPAY_SECRET', 'test-secret'),

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | These URLs are called by the payment gateways to notify us of
    | payment status changes.
    |
    */

    'callbacks' => [
        'jawwal_pay' => '/api/payments/callback/jawwal_pay',
        'palpay' => '/api/payments/callback/palpay',
    ],

];
