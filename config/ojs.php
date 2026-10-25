<?php

return [
    'base_url' => env('OJS_BASE_URL'),
    'api_token' => env('OJS_API_TOKEN'),
    'driver' => env('OJS_DRIVER', 'mock'),
    'fixture_path' => env('OJS_FIXTURE_PATH') ?: base_path('database/fixtures/ojs'),
    'timeout' => env('OJS_TIMEOUT', 10),
    'page_size' => 100,
];
