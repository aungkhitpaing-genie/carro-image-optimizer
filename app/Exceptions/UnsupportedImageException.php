<?php

namespace App\Exceptions;

use Exception;

class UnsupportedImageException extends Exception
{
    public static function forMimeType(?string $mimeType): self
    {
        return new self("Unsupported or missing image mime type: {$mimeType}");
    }

    public static function forExtension(string $extension): self
    {
        return new self("Unsupported image extension: {$extension}");
    }
}
