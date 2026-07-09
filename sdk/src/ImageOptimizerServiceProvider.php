<?php

namespace Carro\ImageOptimizer;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class ImageOptimizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/image-optimizer.php', 'image-optimizer');

        $this->app->singleton(ImageOptimizerClient::class, function (Application $app): ImageOptimizerClient {
            /** @var array{base_url?: string, api_key?: string, timeout?: int, retries?: int} $config */
            $config = $app['config']['image-optimizer'] ?? [];

            return new ImageOptimizerClient(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) ($config['base_url'] ?? ''),
                apiKey: (string) ($config['api_key'] ?? ''),
                timeout: (int) ($config['timeout'] ?? 30),
                retries: (int) ($config['retries'] ?? 2),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/image-optimizer.php' => $this->app->configPath('image-optimizer.php'),
            ], 'image-optimizer-config');
        }
    }
}
