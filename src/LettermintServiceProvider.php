<?php

namespace Lettermint\Laravel;

use Illuminate\Support\Facades\Mail;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LettermintServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('lettermint-laravel')
            ->hasConfigFile();
    }

    public function boot(): void
    {
        parent::boot();

        Mail::extend('lettermint', function (array $config = []) {
            return new LettermintTransportFactory($this->app['lettermint'], $config['options'] ?? []);
        });
    }

    public function register(): void
    {
        parent::register();

        $this->registerLettermintClient();
    }

    protected function registerLettermintClient(): void
    {
        $this->app->singleton(\Lettermint\Lettermint::class, static function (): \Lettermint\Lettermint {
            // A user can configure the api token in the config file or in the services config file.
            $apiToken = config('lettermint.api_token') ?? config('services.lettermint.token');

            if (! is_string($apiToken)) {
                throw ApiTokenNotFoundException::create();
            }

            return new \Lettermint\Lettermint($apiToken);
        });
        $this->app->alias(\Lettermint\Lettermint::class, 'lettermint');
    }

    public function provides(): array
    {
        return [
            ...parent::provides(),
            \Lettermint\Lettermint::class,
        ];
    }
}
