<?php

declare(strict_types=1);

namespace Angou\Angocore;

use Angou\Angocore\Ai\Ai;
use Angou\Angocore\Mail\AngocoreMailTransport;
use Angou\Angocore\Payments\Angopay;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\TransportInterface;

final class AngocoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/angocore.php', 'angocore');

        $this->app->singleton(Client::class, function ($app) {
            return new Client(
                baseUrl: (string) config('angocore.base_url'),
                apiKey: (string) config('angocore.api_key'),
                environment: (string) config('angocore.environment', 'sandbox'),
                timeout: (int) config('angocore.timeout', 10),
                retryTimes: (int) config('angocore.retry_times', 2),
                retryDelayMs: (int) config('angocore.retry_delay_ms', 200),
                inProgressWaitSeconds: (int) config('angocore.in_progress_wait', 15),
            );
        });

        // Its own Client: a completion can take tens of seconds, and a slow
        // (not failed) call must not be re-sent by the shared client's retry.
        $this->app->singleton(Ai::class, function ($app) {
            return new Ai(new Client(
                baseUrl: (string) config('angocore.base_url'),
                apiKey: (string) config('angocore.api_key'),
                environment: (string) config('angocore.environment', 'sandbox'),
                timeout: (int) config('angocore.ai_timeout', 65),
                retryTimes: 0,
                inProgressWaitSeconds: (int) config('angocore.in_progress_wait', 15),
            ));
        });

        $this->app->singleton(Angopay::class, function ($app) {
            return new Angopay(
                $app->make(Client::class),
                (string) config('angocore.webhook_secret', ''),
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/angocore.php' => config_path('angocore.php'),
        ], 'angocore-config');

        Mail::extend('angocore', static function (array $config = []): TransportInterface {
            return new AngocoreMailTransport(
                baseUrl: (string) config('angocore.base_url'),
                apiKey: (string) config('angocore.api_key'),
                environment: (string) config('angocore.environment', 'sandbox'),
                timeout: (int) config('angocore.timeout', 10),
            );
        });
    }
}
