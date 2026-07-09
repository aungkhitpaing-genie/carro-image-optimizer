<?php

use Carro\ImageOptimizer\Data\GenerateVariantsResult;
use Carro\ImageOptimizer\Exceptions\SourceNotFoundException;
use Carro\ImageOptimizer\Exceptions\UnauthorizedException;
use Carro\ImageOptimizer\Exceptions\UnprocessableImageException;
use Carro\ImageOptimizer\ImageOptimizerClient;
use Carro\ImageOptimizer\ImageOptimizerServiceProvider;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

function makeSdkClient(): ImageOptimizerClient
{
    return new ImageOptimizerClient(
        http: app(HttpFactory::class),
        baseUrl: 'https://optimizer.test',
        apiKey: 'secret-key',
        timeout: 5,
        retries: 1,
    );
}

test('sdk generates variants and returns a typed result', function () {
    Http::fake([
        'optimizer.test/api/variants/generate' => Http::response([
            'data' => [
                'disk' => 'local',
                'source_path' => 'uploads/photo.jpg',
                'variants' => [
                    'thumbnail' => 'uploads/variants/photo-thumbnail.jpg',
                    'medium' => 'uploads/variants/photo-medium.jpg',
                ],
                'generated_count' => 2,
            ],
        ]),
    ]);

    $result = makeSdkClient()->generateVariants(
        disk: 'local',
        path: 'uploads/photo.jpg',
        variants: ['thumbnail', 'medium'],
    );

    expect($result)->toBeInstanceOf(GenerateVariantsResult::class)
        ->and($result->generatedCount)->toBe(2)
        ->and($result->variant('thumbnail'))->toBe('uploads/variants/photo-thumbnail.jpg')
        ->and($result->variant('missing'))->toBeNull();

    Http::assertSent(fn ($request) => $request->hasHeader('X-Api-Key', 'secret-key')
        && $request->url() === 'https://optimizer.test/api/variants/generate'
        && $request['disk'] === 'local'
        && $request['variants'] === ['thumbnail', 'medium']);
});

test('sdk accepts a cdn url as the source path', function () {
    Http::fake([
        'optimizer.test/*' => Http::response([
            'data' => [
                'disk' => 'local',
                'source_path' => 'https://cdn.example.com/uploads/photo.jpg',
                'variants' => ['thumbnail' => 'uploads/variants/photo-thumbnail.jpg'],
                'generated_count' => 1,
            ],
        ]),
    ]);

    $result = makeSdkClient()->generateVariants(
        disk: 'local',
        path: 'https://cdn.example.com/uploads/photo.jpg',
        variants: ['thumbnail'],
    );

    expect($result->generatedCount)->toBe(1);

    Http::assertSent(fn ($request) => $request['path'] === 'https://cdn.example.com/uploads/photo.jpg');
});

test('sdk maps a 404 to SourceNotFoundException', function () {
    Http::fake([
        '*' => Http::response(['message' => 'Source image not found on disk [local]: missing.jpg'], 404),
    ]);

    makeSdkClient()->generateVariants(disk: 'local', path: 'missing.jpg');
})->throws(SourceNotFoundException::class, 'Source image not found on disk [local]: missing.jpg');

test('sdk maps a 401 to UnauthorizedException', function () {
    Http::fake(['*' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    makeSdkClient()->status();
})->throws(UnauthorizedException::class, 'Unauthenticated.');

test('sdk maps a 422 validation error to UnprocessableImageException carrying the errors', function () {
    Http::fake([
        '*' => Http::response([
            'message' => 'The selected disk is not configured.',
            'errors' => ['disk' => ['The selected disk is not configured.']],
        ], 422),
    ]);

    try {
        makeSdkClient()->generateVariants(disk: 'nope', path: 'x.jpg');
        test()->fail('Expected UnprocessableImageException to be thrown.');
    } catch (UnprocessableImageException $e) {
        expect($e->errors)->toHaveKey('disk')
            ->and($e->getMessage())->toBe('The selected disk is not configured.');
    }
});

test('sdk reads the status endpoint', function () {
    Http::fake([
        'optimizer.test/api/status' => Http::response(['status' => 'ok', 'service' => 'Image Optimizer']),
    ]);

    expect(makeSdkClient()->status())->toBe(['status' => 'ok', 'service' => 'Image Optimizer']);
});

test('sdk service provider binds the client from config', function () {
    config()->set('image-optimizer', [
        'base_url' => 'https://optimizer.test',
        'api_key' => 'secret-key',
        'timeout' => 10,
        'retries' => 2,
    ]);

    app()->register(ImageOptimizerServiceProvider::class);

    expect(app(ImageOptimizerClient::class))->toBeInstanceOf(ImageOptimizerClient::class);
});
