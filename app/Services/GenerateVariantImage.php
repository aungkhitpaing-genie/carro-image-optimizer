<?php

namespace App\Services;

use App\Exceptions\SourceImageNotFoundException;
use App\Exceptions\UnsupportedImageException;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\File as FacadesFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\UnableToCheckExistence;
use Spatie\Image\Enums\Constraint;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

class GenerateVariantImage
{
    /**
     * @throws Throwable
     * @throws UnsupportedImageException
     * @throws SourceImageNotFoundException
     */
    public function generate(
        string $disk,
        string $sourcePath,
        ?string $mimeType = null,
        ?string $fileName = null,
        ?array $only = null,
        bool $overwrite = false,
    ): array {
        $isRemote = $this->isRemoteUrl($sourcePath);
        $storageKey = $isRemote ? $this->deriveStorageKey($sourcePath) : $sourcePath;

        $mimeType = $mimeType ?: $this->guessMimeType($storageKey);
        $fileName = $fileName ?: basename($storageKey);

        if (! $this->isImageMimeType($mimeType)) {
            throw UnsupportedImageException::forMimeType($mimeType);
        }

        $extension = $this->resolveExtension($fileName, $mimeType);

        if (! $this->isSupportedExtension($extension)) {
            throw UnsupportedImageException::forExtension($extension);
        }

        $variants = $this->resolveVariants($only);
        $filesystem = Storage::disk($disk);

        $sourceContents = $isRemote
            ? $this->fetchRemoteContents($disk, $sourcePath)
            : $this->readDiskContents($filesystem, $disk, $sourcePath);

        $tmpSource = $this->downloadToTemp($sourceContents, $extension, 'src');
        $generated = [];

        try {
            foreach ($variants as $key => $config) {
                $variantPath = $this->buildVariantPath($storageKey, $key, $extension);

                if (! $overwrite && $filesystem->exists($variantPath)) {
                    $generated[$key] = $variantPath;

                    continue;
                }

                $tmpVariant = $this->buildTempPath($extension, $key);

                try {
                    $this->makeVariant($tmpSource, $tmpVariant, $config);

                    $filesystem->put(
                        $variantPath,
                        file_get_contents($tmpVariant),
                        ['ContentType' => $this->mimeTypeForExtension($extension)],
                    );

                    $generated[$key] = $variantPath;
                } catch (Throwable $e) {
                    if ($this->isTransientError($e)) {
                        throw $e;
                    }

                    Log::error("[GenerateVariantImage] Failed variant '{$key}' for {$sourcePath}: ".$e->getMessage());
                } finally {
                    $this->cleanup($tmpVariant);
                }
            }
        } finally {
            $this->cleanup($tmpSource);
        }

        if ($generated !== []) {
            Log::info('[GenerateVariantImage] Generated '.count($generated)." variants for {$sourcePath}");
        }

        return $generated;
    }

    /**
     * @param array{width: int, height: ?int, fit: Fit} $config
     */
    protected function makeVariant(string $source, string $destination, array $config): void
    {
        $image = Image::load($source);

        if (! empty($config['height'])) {
            $image->fit($config['fit'], $config['width'], $config['height']);
        } else {
            $image->width($config['width'], [
                Constraint::PreserveAspectRatio,
                Constraint::DoNotUpsize,
            ]);
        }

        $image->quality((int) config('image-variants.quality', 82))
            ->optimize()
            ->save($destination);
    }

    protected function buildVariantPath(string $sourcePath, string $key, string $extension): string
    {
        $directory = trim(dirname($sourcePath), '.');
        $directory = $directory === '' ? '' : rtrim($directory, '/').'/';
        $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
        $variantDir = trim((string) config('image-variants.variant_dir', 'variants'), '/');

        return "{$directory}{$variantDir}/{$filename}-{$key}.{$extension}";
    }

    protected function isRemoteUrl(string $path): bool
    {
        return Str::startsWith($path, ['http://', 'https://']);
    }

    /**
     * Map a remote URL to the disk-relative key used for reading existing
     * variants and writing new ones (query string and host are discarded).
     */
    protected function deriveStorageKey(string $url): string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return ltrim(rawurldecode($path), '/');
    }

    protected function readDiskContents(Filesystem $filesystem, string $disk, string $path): string
    {
        if (! $filesystem->exists($path)) {
            throw SourceImageNotFoundException::forPath($disk, $path);
        }

        return (string) $filesystem->get($path);
    }

    /**
     * @throws SourceImageNotFoundException
     * @throws \Illuminate\Http\Client\RequestException
     */
    protected function fetchRemoteContents(string $disk, string $url): string
    {
        $duration = (int) config('image-variants.remote_timeout', 30);
        $response = Http::timeout($duration)->get($url);

        if ($response->clientError()) {
            throw SourceImageNotFoundException::forPath($disk, $url);
        }

        // Bubble up 5xx as a transient failure for the caller to retry.
        $response->throw();

        return $response->body();
    }

    protected function isTransientError(Throwable $e): bool
    {
        return $e instanceof UnableToCheckExistence
            || ($e->getPrevious() && str_contains($e->getPrevious()->getMessage(), '503 Service Unavailable'))
            || str_contains($e->getMessage(), '503 Service Unavailable')
            || str_contains($e->getMessage(), '500 Internal Server Error')
            || str_contains($e->getMessage(), 'timed out');
    }

    /**
     * @param array<string>|null $only
     * @return array<string, array{width: int, height: ?int, fit: Fit}>
     */
    protected function resolveVariants(?array $only): array
    {
        /** @var array<string, array{width: int, height: ?int, fit: Fit}> $variants */
        $variants = config('image-variants.variants', []);

        if (empty($only)) {
            return $variants;
        }

        return array_intersect_key($variants, array_flip($only));
    }

    protected function resolveExtension(string $fileName, string $mimeType): string
    {
        $extension = strtolower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === '') {
            $extension = strtolower(Str::after($mimeType, '/'));
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    protected function isSupportedExtension(string $extension): bool
    {
        return in_array($extension, ['jpg', 'png', 'webp', 'gif'], true);
    }

    protected function isImageMimeType(?string $mimeType): bool
    {
        return Str::startsWith((string) $mimeType, 'image/');
    }

    protected function guessMimeType(string $path): ?string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return $this->mimeTypeForExtension($extension === 'jpeg' ? 'jpg' : $extension);
    }

    protected function mimeTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'application/octet-stream',
        };
    }

    protected function downloadToTemp(string $contents, string $extension, string $hint): string
    {
        $path = $this->buildTempPath($extension, $hint);
        file_put_contents($path, $contents);

        return $path;
    }

    protected function buildTempPath(string $extension, string $hint): string
    {
        $dir = storage_path('tmp');

        if (! FacadesFile::exists($dir)) {
            FacadesFile::makeDirectory($dir, 0755, true);
        }

        return $dir.'/'.Str::random()."-{$hint}.{$extension}";
    }

    protected function cleanup(?string $path): void
    {
        if (! empty($path) && FacadesFile::exists($path)) {
            unlink($path);
        }
    }
}
