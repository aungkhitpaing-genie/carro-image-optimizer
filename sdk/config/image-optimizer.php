<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The root URL of the Carro Image Optimizer service, e.g.
    | https://images.carro.test. Endpoint paths are appended automatically.
    |
    */

    'base_url' => env('IMAGE_OPTIMIZER_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Sent as the X-Api-Key header on every request. This must match the
    | API_KEY configured on the image optimizer service.
    |
    */

    'api_key' => env('IMAGE_OPTIMIZER_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout & Retries
    |--------------------------------------------------------------------------
    |
    | Timeout is in seconds. Retries apply to connection-level failures
    | (timeouts, refused connections) only.
    |
    */

    'timeout' => (int) env('IMAGE_OPTIMIZER_TIMEOUT', 30),

    'retries' => (int) env('IMAGE_OPTIMIZER_RETRIES', 2),

];
