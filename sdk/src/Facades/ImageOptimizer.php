<?php

namespace Carro\ImageOptimizer\Facades;

use Carro\ImageOptimizer\Data\GenerateVariantsResult;
use Carro\ImageOptimizer\ImageOptimizerClient;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array{status: string, service: ?string} status()
 * @method static GenerateVariantsResult generateVariants(string $disk, string $path, ?string $mimeType = null, ?string $fileName = null, ?array $variants = null, bool $overwrite = false)
 *
 * @see ImageOptimizerClient
 */
class ImageOptimizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ImageOptimizerClient::class;
    }
}
