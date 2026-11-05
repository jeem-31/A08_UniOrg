<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public Content Cache
    |--------------------------------------------------------------------------
    |
    | Cache entries are namespaced by a version key. Updating content advances
    | the relevant version, immediately making all previous entries unreachable
    | while Redis removes them naturally after the configured TTL.
    |
    */

    'prefix' => env('CONTENT_CACHE_PREFIX', 'content'),

    'ttl_minutes' => (int) env('CONTENT_CACHE_TTL', 10),
];
