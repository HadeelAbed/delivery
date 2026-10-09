<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Push (VAPID)
    |--------------------------------------------------------------------------
    |
    | Browser push delivery (REQ-14) is additive: in-app database
    | notifications always fire; push is attempted only when all three
    | VAPID values are configured. Keys live in the environment only —
    | never commit real values. Generate a pair with:
    |
    |   php artisan webpush:vapid
    |
    */

    'vapid_public_key' => env('VAPID_PUBLIC_KEY'),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY'),
    'vapid_subject' => env('VAPID_SUBJECT'),

];
