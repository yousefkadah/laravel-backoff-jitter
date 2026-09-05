<?php

namespace YousefKadah\BackoffJitter;

use Illuminate\Queue\Queue;
use Illuminate\Support\ServiceProvider;

class BackoffJitterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/backoff-jitter.php', 'backoff-jitter');

        $this->app->singleton(JitterResolver::class, function ($app) {
            $config = $app['config']->get('backoff-jitter', []);

            $default = $config['default_ratio'] ?? null;

            return new JitterResolver(
                is_null($default) ? null : (float) $default,
                (int) ($config['unbounded_attempts'] ?? 10),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/backoff-jitter.php' => $this->app->configPath('backoff-jitter.php'),
            ], 'backoff-jitter-config');
        }

        if (! $this->app['config']->get('backoff-jitter.enabled', true)) {
            return;
        }

        $resolver = $this->app->make(JitterResolver::class);

        Queue::createPayloadUsing(
            fn ($connection, $queue, $payload) => $resolver->apply($payload)
        );
    }
}
