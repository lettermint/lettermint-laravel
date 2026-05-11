<?php

namespace Lettermint\Laravel;

use Illuminate\Support\Facades\Mail;
use Lettermint\Client\ApiClient;
use Lettermint\Endpoints\EmailEndpoint;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Exceptions\TeamApiTokenNotFoundException;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Lettermint\Lettermint as LettermintSdk;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LettermintServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('lettermint')
            ->hasConfigFile()
            ->hasRoute('webhooks');
    }

    public function boot(): void
    {
        parent::boot();

        $app = $this->app;
        Mail::extend('lettermint', function (array $config = []) use ($app) {
            return new LettermintTransportFactory($app['lettermint'], $config);
        });
    }

    public function register(): void
    {
        parent::register();

        $this->registerLettermintEmailEndpoint();
        $this->registerLettermintApiClient();
    }

    protected function registerLettermintEmailEndpoint(): void
    {
        $this->app->bind(EmailEndpoint::class, static function (): EmailEndpoint {
            // A user can configure the project token in the config file or in the services config file.
            $projectToken = config('lettermint.token') ?? config('services.lettermint.token');

            if (! is_string($projectToken)) {
                throw ApiTokenNotFoundException::create();
            }

            return LettermintSdk::email($projectToken);
        });
        $this->app->alias(EmailEndpoint::class, 'lettermint');
    }

    protected function registerLettermintApiClient(): void
    {
        $this->app->bind(ApiClient::class, static function (): ApiClient {
            $apiToken = config('lettermint.api_token') ?? config('services.lettermint.api_token');

            if (! is_string($apiToken)) {
                throw TeamApiTokenNotFoundException::create();
            }

            return LettermintSdk::api($apiToken);
        });
        $this->app->alias(ApiClient::class, 'lettermint.api');
    }

    public function provides(): array
    {
        return [
            ...parent::provides(),
            ApiClient::class,
            EmailEndpoint::class,
        ];
    }
}
