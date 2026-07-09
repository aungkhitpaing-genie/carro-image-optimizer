<?php

use App\Services\GenerateVariantImage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function createTestJpegContents(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagejpeg($image, null, 90);
    $contents = ob_get_clean() ?: '';
    imagedestroy($image);

    return $contents;
}

function createTestPngContents(int $width = 800, int $height = 600): string
{
    $image = imagecreatetruecolor($width, $height);
    ob_start();
    imagepng($image);
    $contents = ob_get_clean() ?: '';
    imagedestroy($image);

    return $contents;
}

test('generate variants endpoint requires an api key', function () {
    $this->postJson('/api/variants/generate', [])
        ->assertUnauthorized();
});

test('generate variants endpoint validates the request payload', function () {
    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disk', 'path']);
});

test('generate variants endpoint returns not found when source is missing', function () {
    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'missing/photo.jpg',
            'mime_type' => 'image/jpeg',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Source image not found on disk [local]: missing/photo.jpg');
});

test('generate variants endpoint returns unprocessable for unsupported images', function () {
    Storage::disk('local')->put('documents/readme.txt', 'not an image');

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'documents/readme.txt',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Unsupported or missing image mime type: application/octet-stream');
});

test('generate variants endpoint generates requested variants', function () {
    $sourcePath = 'uploads/photo.jpg';
    Storage::disk('local')->put($sourcePath, createTestJpegContents());

    $response = $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'mime_type' => 'image/jpeg',
            'variants' => ['thumbnail', 'medium'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.disk', 'local')
        ->assertJsonPath('data.source_path', $sourcePath)
        ->assertJsonPath('data.generated_count', 2);

    $variants = $response->json('data.variants');

    expect($variants)->toHaveKeys(['thumbnail', 'medium'])
        ->and(Storage::disk('local')->exists($variants['thumbnail']))->toBeTrue()
        ->and(Storage::disk('local')->exists($variants['medium']))->toBeTrue()
        ->and($variants['thumbnail'])->toBe('uploads/variants/photo-thumbnail.jpg')
        ->and($variants['medium'])->toBe('uploads/variants/photo-medium.jpg');
});

test('generate variants service skips existing variants unless overwrite is enabled', function () {
    $sourcePath = 'uploads/photo.jpg';
    Storage::disk('local')->put($sourcePath, createTestJpegContents());

    $service = app(GenerateVariantImage::class);

    $firstRun = $service->generate(
        disk: 'local',
        sourcePath: $sourcePath,
        mimeType: 'image/jpeg',
        only: ['thumbnail'],
    );

    Storage::disk('local')->put($firstRun['thumbnail'], 'stale-variant');

    $secondRun = $service->generate(
        disk: 'local',
        sourcePath: $sourcePath,
        mimeType: 'image/jpeg',
        only: ['thumbnail'],
        overwrite: false,
    );

    expect($secondRun['thumbnail'])->toBe($firstRun['thumbnail'])
        ->and(Storage::disk('local')->get($secondRun['thumbnail']))->toBe('stale-variant');

    $thirdRun = $service->generate(
        disk: 'local',
        sourcePath: $sourcePath,
        mimeType: 'image/jpeg',
        only: ['thumbnail'],
        overwrite: true,
    );

    expect(Storage::disk('local')->get($thirdRun['thumbnail']))->not->toBe('stale-variant');
});

test('generate variants endpoint rejects an unconfigured disk', function () {
    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'does-not-exist',
            'path' => 'uploads/photo.jpg',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disk'])
        ->assertJsonPath('errors.disk.0', 'The selected disk is not configured.');
});

test('generate variants endpoint rejects invalid variant names', function () {
    Storage::disk('local')->put('uploads/photo.jpg', createTestJpegContents());

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'variants' => ['thumbnail', 'gigantic'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['variants.1' => 'One or more variant names are invalid.']);
});

test('generate variants endpoint rejects a non-image mime type', function () {
    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'uploads/photo.jpg',
            'mime_type' => 'application/pdf',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['mime_type']);
});

test('generate variants endpoint generates every configured variant when none are requested', function () {
    $sourcePath = 'uploads/photo.jpg';
    Storage::disk('local')->put($sourcePath, createTestJpegContents());

    $expectedKeys = array_keys(config('image-variants.variants'));

    $response = $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'mime_type' => 'image/jpeg',
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.generated_count', count($expectedKeys));

    expect($response->json('data.variants'))->toHaveKeys($expectedKeys);
});

test('generate variants endpoint overwrites existing variants when requested', function () {
    $sourcePath = 'uploads/photo.jpg';
    Storage::disk('local')->put($sourcePath, createTestJpegContents());

    $variantPath = 'uploads/variants/photo-thumbnail.jpg';
    Storage::disk('local')->put($variantPath, 'stale-variant');

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'mime_type' => 'image/jpeg',
            'variants' => ['thumbnail'],
            'overwrite' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.generated_count', 1);

    expect(Storage::disk('local')->get($variantPath))->not->toBe('stale-variant');
});

test('generate variants endpoint generates variants for png sources', function () {
    $sourcePath = 'uploads/logo.png';
    Storage::disk('local')->put($sourcePath, createTestPngContents());

    $response = $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'variants' => ['thumbnail'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.variants.thumbnail', 'uploads/variants/logo-thumbnail.png');

    expect(Storage::disk('local')->exists($response->json('data.variants.thumbnail')))->toBeTrue();
});

test('generate variants endpoint resolves the extension from file_name when the path has none', function () {
    $sourcePath = 'uploads/object-key-without-extension';
    Storage::disk('local')->put($sourcePath, createTestJpegContents());

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'mime_type' => 'image/jpeg',
            'file_name' => 'photo.jpg',
            'variants' => ['thumbnail'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.variants.thumbnail', 'uploads/variants/object-key-without-extension-thumbnail.jpg');
});

test('generate variants endpoint fetches a remote cdn url and writes variants to the disk', function () {
    Http::fake([
        'cdn.example.com/*' => Http::response(createTestJpegContents(), 200, ['Content-Type' => 'image/jpeg']),
    ]);

    $response = $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'https://cdn.example.com/uploads/photo.jpg?v=2',
            'mime_type' => 'image/jpeg',
            'variants' => ['thumbnail', 'medium'],
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.generated_count', 2)
        ->assertJsonPath('data.variants.thumbnail', 'uploads/variants/photo-thumbnail.jpg')
        ->assertJsonPath('data.variants.medium', 'uploads/variants/photo-medium.jpg');

    $variants = $response->json('data.variants');

    expect(Storage::disk('local')->exists($variants['thumbnail']))->toBeTrue()
        ->and(Storage::disk('local')->exists($variants['medium']))->toBeTrue();
});

test('generate variants endpoint returns not found when the remote source is missing', function () {
    Http::fake([
        'cdn.example.com/*' => Http::response('not found', 404),
    ]);

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => 'https://cdn.example.com/uploads/missing.jpg',
            'mime_type' => 'image/jpeg',
        ])
        ->assertNotFound()
        ->assertJsonPath('message', 'Source image not found on disk [local]: https://cdn.example.com/uploads/missing.jpg');
});

test('generate variants endpoint returns unprocessable when no variants could be generated', function () {
    $sourcePath = 'uploads/corrupt.jpg';
    Storage::disk('local')->put($sourcePath, 'not actually a jpeg');

    $this->withHeader('X-Api-Key', 'test-api-key')
        ->postJson('/api/variants/generate', [
            'disk' => 'local',
            'path' => $sourcePath,
            'mime_type' => 'image/jpeg',
            'variants' => ['thumbnail'],
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'No variants were generated.');
});
