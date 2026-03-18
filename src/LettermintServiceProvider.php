<?php

namespace Lettermint\Laravel;

use Illuminate\Support\Facades\Mail;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Lettermint\Lettermint;
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

        $this->registerLettermintClient();
    }

    protected function registerLettermintClient(): void
    {
        $this->app->singleton(Lettermint::class, static function (): Lettermint {
            // A user can configure the api token in the config file or in the services config file.
            $apiToken = config('lettermint.token') ?? config('services.lettermint.token');

            if (! is_string($apiToken)) {
                throw ApiTokenNotFoundException::create();
            }

            return new Lettermint($apiToken);
        });
        $this->app->alias(Lettermint::class, 'lettermint');
    }

    public function provides(): array
    {
        return [
            ...parent::provides(),
            Lettermint::class,
        ];
    }
}
