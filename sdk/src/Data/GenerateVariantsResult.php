<?php

namespace Carro\ImageOptimizer\Data;

class GenerateVariantsResult
{
    /**
     * @param  array<string, string>  $variants  Map of variant key to its stored path.
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $sourcePath,
        public readonly array $variants,
        public readonly int $generatedCount,
    ) {}

    /**
     * @param  array{disk: string, source_path: string, variants: array<string, string>, generated_count: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            disk: $data['disk'],
            sourcePath: $data['source_path'],
            variants: $data['variants'],
            generatedCount: $data['generated_count'],
        );
    }

    /**
     * Return the stored path for a single variant key, or null when absent.
     */
    public function variant(string $key): ?string
    {
        return $this->variants[$key] ?? null;
    }
}
