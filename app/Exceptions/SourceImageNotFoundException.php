<?php

namespace App\Exceptions;

use Exception;

class SourceImageNotFoundException extends Exception
{
    public static function forPath(string $disk, string $path): self
    {
        return new self("Source image not found on disk [{$disk}]: {$path}");
    }
}
