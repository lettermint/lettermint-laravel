<?php

namespace Lettermint\Laravel;

use GuzzleHttp\ClientInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Mail;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Lettermint\Lettermint;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LettermintServiceProvider extends PackageServiceProvider
{
    /**
     * Container key for an optional Guzzle client used by the Lettermint
     * client, for example one with a proxy. Bind a GuzzleHttp\ClientInterface
     * under this key before the client is first resolved.
     */
    public const HTTP_CLIENT = 'lettermint.http_client';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('lettermint')
            ->hasConfigFile();
    }

    public function bootingPackage(): void
    {
        // hasRoute cannot live in configurePackage: that hook runs before the
        // package config is merged, so webhooks.enabled would not be readable.
        if (config('lettermint.webhooks.enabled', true)) {
            $this->package->hasRoute('webhooks');
        }
    }

    public function boot(): void
    {
        parent::boot();

        $app = $this->app;
        Mail::extend('lettermint', function (array $config = []) use ($app) {
            // Fail when the mailer is created, not on the first send, so a
            // missing project token surfaces as a configuration error.
            if (self::sendingToken() === null) {
                throw ApiTokenNotFoundException::create();
            }

            return new LettermintTransportFactory($app->make(Lettermint::class), $config);
        });
    }

    public function register(): void
    {
        parent::register();

        // The client holds no message state, so one instance is shared by the
        // mail transport, the facade and the application, also in queue
        // workers and Octane. It refuses serialization because it holds the
        // tokens: resolve it where it is used, never store it on a queued job.
        $this->app->singleton(Lettermint::class, static function (Application $app): Lettermint {
            $sendingToken = self::sendingToken();
            $teamToken = self::teamToken();

            if ($sendingToken === null && $teamToken === null) {
                throw ApiTokenNotFoundException::noTokens();
            }

            $httpClient = $app->bound(self::HTTP_CLIENT) ? $app->make(self::HTTP_CLIENT) : null;

            return new Lettermint(
                sendingToken: $sendingToken,
                teamToken: $teamToken,
                timeout: (float) config('lettermint.timeout', 15),
                httpClient: $httpClient instanceof ClientInterface ? $httpClient : null,
            );
        });
        $this->app->alias(Lettermint::class, 'lettermint');
    }

    /**
     * The project sending token from config/lettermint.php, or else from
     * config/services.php. Empty values count as not configured.
     */
    public static function sendingToken(): ?string
    {
        return self::firstToken(config('lettermint.token'), config('services.lettermint.token'));
    }

    /**
     * The team API token from config/lettermint.php, or else from
     * config/services.php. Empty values count as not configured.
     */
    public static function teamToken(): ?string
    {
        return self::firstToken(config('lettermint.api_token'), config('services.lettermint.api_token'));
    }

    private static function firstToken(mixed ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    public function provides(): array
    {
        return [
            ...parent::provides(),
            Lettermint::class,
            'lettermint',
        ];
    }
}
