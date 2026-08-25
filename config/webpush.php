<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAPID credentials
    |--------------------------------------------------------------------------
    |
    | Identify this application server to the browser push services (FCM,
    | Mozilla, WNS). Generate a pair with `php artisan webpush:vapid` and
    | copy the resulting keys into the .env file. Without them, push
    | delivery is silently disabled — the app still records the in-app
    | notification, it just never leaves the server.
    |
    */

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'http://localhost')),
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery defaults
    |--------------------------------------------------------------------------
    |
    | `ttl` is how long (seconds) the push service should keep retrying a
    | message for a device that is offline. SOS requests are time-critical,
    | so the default is deliberately short.
    |
    */

    'ttl' => (int) env('WEBPUSH_TTL', 3600),

    'urgency' => env('WEBPUSH_URGENCY', 'high'),

    'timeout' => (int) env('WEBPUSH_TIMEOUT', 10),

];
