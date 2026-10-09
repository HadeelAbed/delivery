<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google Maps (REQ-13)
    |--------------------------------------------------------------------------
    |
    | The browser key is public by design (restricted by HTTP referrer in
    | the Google console). The server key MUST stay server-side: it is
    | only ever used by GoogleMapsProvider via the HTTP client, never
    | rendered into Blade, JavaScript, API responses, or logs.
    |
    */

    'browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
    'server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
    'base_url' => env('GOOGLE_MAPS_BASE_URL', 'https://maps.googleapis.com'),
    'timeout' => (int) env('GOOGLE_MAPS_TIMEOUT', 5),
    'cache_ttl' => (int) env('GOOGLE_MAPS_CACHE_TTL', 60),

];
