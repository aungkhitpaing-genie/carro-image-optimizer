<?php

use Spatie\Image\Enums\Fit;

return [

    /*
    |--------------------------------------------------------------------------
    | Image Variant Presets
    |--------------------------------------------------------------------------
    |
    | WordPress-style image sizes used when generating variants. FIT_CROP
    | keeps exact dimensions (thumbnail). FIT_MAX fits inside the box
    | without up-scaling. A null height constrains width only.
    |
    */

    'variants' => [
        'thumbnail' => ['width' => 150, 'height' => 150, 'fit' => Fit::Crop],
        'medium' => ['width' => 300, 'height' => 300, 'fit' => Fit::Max],
        'medium_large' => ['width' => 768, 'height' => null, 'fit' => Fit::Max],
        'large' => ['width' => 1024, 'height' => 1024, 'fit' => Fit::Max],
        'x_large' => ['width' => 1536, 'height' => 1536, 'fit' => Fit::Max],
        'xx_large' => ['width' => 2048, 'height' => 2048, 'fit' => Fit::Max],
    ],

    'quality' => 82,

    'variant_dir' => 'variants',

    /*
    |--------------------------------------------------------------------------
    | Remote Source Timeout
    |--------------------------------------------------------------------------
    |
    | When the source path is an http(s) URL (e.g. a CDN URL), the original
    | image is fetched over HTTP. This is the request timeout in seconds.
    |
    */

    'remote_timeout' => 30,

];
