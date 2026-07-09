# Carro Image Optimizer SDK

A PHP / Laravel client for the Carro Image Optimizer API. Wraps the
`POST /api/variants/generate` and `GET /api/status` endpoints with a typed
client, a Facade, typed exceptions, and a response DTO.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

While the package lives inside the optimizer repo, install it via a path
repository in the consumer app's `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "../carro-image-optimizer/sdk" }
    ],
    "require": {
        "carro/image-optimizer-sdk": "*"
    }
}
```

```bash
composer require carro/image-optimizer-sdk
```

The service provider and `ImageOptimizer` facade are registered automatically
via Laravel package discovery.

## Configuration

Set the service URL and API key in the consumer app's `.env`:

```dotenv
IMAGE_OPTIMIZER_URL=https://images.carro.test
IMAGE_OPTIMIZER_KEY=the-shared-api-key
IMAGE_OPTIMIZER_TIMEOUT=30
IMAGE_OPTIMIZER_RETRIES=2
```

`IMAGE_OPTIMIZER_KEY` must match the `API_KEY` configured on the optimizer
service. To customise the config file, publish it:

```bash
php artisan vendor:publish --tag=image-optimizer-config
```

## Usage

### Generate variants

```php
use Carro\ImageOptimizer\Facades\ImageOptimizer;

$result = ImageOptimizer::generateVariants(
    disk: 's3',
    path: 'uploads/photo.jpg',          // a disk key, or an http(s) CDN URL
    variants: ['thumbnail', 'medium'],  // omit to generate every configured preset
    overwrite: false,                   // re-generate variants that already exist
);

$result->generatedCount;          // int
$result->variants;                // ['thumbnail' => 'uploads/variants/photo-thumbnail.jpg', ...]
$result->variant('thumbnail');    // 'uploads/variants/photo-thumbnail.jpg' or null
$result->sourcePath;              // 'uploads/photo.jpg'
$result->disk;                    // 's3'
```

`path` accepts either a disk-relative key or a CDN URL. When a URL is given the
optimizer fetches the original over HTTP and writes the variants to `disk`
under the key derived from the URL path.

### Status

```php
ImageOptimizer::status(); // ['status' => 'ok', 'service' => 'Image Optimizer']
```

### Without the facade

Resolve the client from the container, or construct it directly:

```php
use Carro\ImageOptimizer\ImageOptimizerClient;

$client = app(ImageOptimizerClient::class);
$result = $client->generateVariants(disk: 's3', path: 'uploads/photo.jpg');
```

## Error handling

Every failure throws a subclass of `ImageOptimizerException`:

| HTTP status        | Exception                       | Notes                                              |
| ------------------ | ------------------------------- | -------------------------------------------------- |
| 401                | `UnauthorizedException`         | API key missing or rejected                        |
| 404                | `SourceNotFoundException`       | Source image not found on the disk or remote URL   |
| 422                | `UnprocessableImageException`   | Validation failed / unsupported image; see `errors`|
| 5xx, timeouts      | `ImageOptimizerException`       | Connection failures are retried first              |

```php
use Carro\ImageOptimizer\Exceptions\SourceNotFoundException;
use Carro\ImageOptimizer\Exceptions\UnprocessableImageException;

try {
    $result = ImageOptimizer::generateVariants(disk: 's3', path: 'uploads/photo.jpg');
} catch (SourceNotFoundException $e) {
    // original image is missing
} catch (UnprocessableImageException $e) {
    $e->errors; // ['disk' => ['The selected disk is not configured.'], ...]
}
```

## Testing in consumer apps

The SDK is built on Laravel's HTTP client, so fake it in tests:

```php
use Illuminate\Support\Facades\Http;

Http::fake([
    '*/api/variants/generate' => Http::response([
        'data' => [
            'disk' => 's3',
            'source_path' => 'uploads/photo.jpg',
            'variants' => ['thumbnail' => 'uploads/variants/photo-thumbnail.jpg'],
            'generated_count' => 1,
        ],
    ]),
]);
```
