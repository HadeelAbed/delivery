<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Delivery Platform Configuration
    |--------------------------------------------------------------------------
    |
    | Fixed delivery fee and fixed driver earnings per order (Decision C-04).
    | Both values are configurable here and via environment variables.
    |
    */

    'currency' => env('DELIVERY_CURRENCY', 'ILS'),

    'delivery_fee' => (float) env('DELIVERY_FEE', 15.00),

    'driver_earnings' => (float) env('DRIVER_EARNINGS', 10.00),

    /*
    |--------------------------------------------------------------------------
    | Performance Targets
    |--------------------------------------------------------------------------
    |
    | Order placement and status updates must be reflected within 3 seconds
    | (Decision C-03). Driver location pings target every 5-10 seconds.
    |
    */

    'status_propagation_seconds' => 3,

    'driver_ping_interval_seconds' => 7,

    /*
    |--------------------------------------------------------------------------
    | Driver Assignment
    |--------------------------------------------------------------------------
    |
    | Assign the nearest available driver first (Decision C-05).
    |
    */

    'assignment_strategy' => 'nearest_available',

    'assignment_radius_km' => (float) env('ASSIGNMENT_RADIUS_KM', 25.0),

];
