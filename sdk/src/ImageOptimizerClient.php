<?php

namespace Carro\ImageOptimizer;

use Carro\ImageOptimizer\Data\GenerateVariantsResult;
use Carro\ImageOptimizer\Exceptions\ImageOptimizerException;
use Carro\ImageOptimizer\Exceptions\SourceNotFoundException;
use Carro\ImageOptimizer\Exceptions\UnauthorizedException;
use Carro\ImageOptimizer\Exceptions\UnprocessableImageException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;

class ImageOptimizerClient
{
    public function __construct(
        protected HttpFactory $http,
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout = 30,
        protected int $retries = 2,
    ) {}

    /**
     * Fetch the service health/status payload.
     *
     * @return array{status: string, service: ?string}
     *
     * @throws ImageOptimizerException
     */
    public function status(): array
    {
        return $this->request('get', '/api/status')->json();
    }

    /**
     * Generate image variants for a source on the given disk (or a CDN URL).
     *
     * @param  array<int, string>|null  $variants  Variant keys to generate; null generates all configured presets.
     *
     * @throws SourceNotFoundException The source could not be found (404).
     * @throws UnprocessableImageException Validation failed or the image was unsupported (422).
     * @throws UnauthorizedException The API key was rejected (401).
     * @throws ImageOptimizerException Any other transport or server failure.
     */
    public function generateVariants(
        string $disk,
        string $path,
        ?string $mimeType = null,
        ?string $fileName = null,
        ?array $variants = null,
        bool $overwrite = false,
    ): GenerateVariantsResult {
        $payload = array_filter([
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'variants' => $variants,
            'overwrite' => $overwrite,
        ], static fn ($value): bool => $value !== null);

        $response = $this->request('post', '/api/variants/generate', $payload);

        return GenerateVariantsResult::fromArray($response->json('data'));
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ImageOptimizerException
     */
    protected function request(string $method, string $uri, array $payload = []): Response
    {
        try {
            $response = $this->pendingRequest()->{$method}($uri, $payload);
        } catch (ConnectionException $e) {
            throw new ImageOptimizerException(
                "Could not reach the image optimizer service: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $this->throwForStatus($response);
    }

    /**
     * @throws ImageOptimizerException
     */
    protected function throwForStatus(Response $response): never
    {
        $message = (string) ($response->json('message') ?? $response->body());

        throw match ($response->status()) {
            401 => new UnauthorizedException($message !== '' ? $message : 'Unauthenticated.'),
            404 => new SourceNotFoundException($message),
            422 => new UnprocessableImageException($message, (array) $response->json('errors', [])),
            default => new ImageOptimizerException(
                $message !== '' ? $message : "Request failed with status {$response->status()}.",
                $response->status(),
            ),
        };
    }

    protected function pendingRequest(): PendingRequest
    {
        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->retry(1 + max(0, $this->retries), 200, throw: false)
            ->withHeaders(['X-Api-Key' => $this->apiKey])
            ->acceptJson();
    }
}
