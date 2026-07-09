<?php

namespace Carro\ImageOptimizer\Exceptions;

class UnprocessableImageException extends ImageOptimizerException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(string $message, public readonly array $errors = [])
    {
        parent::__construct($message, 422);
    }
}
