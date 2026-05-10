<?php

namespace Lettermint\Laravel;

use Illuminate\Support\Facades\Mail;
use Lettermint\Endpoints\EmailEndpoint;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
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
    }

    protected function registerLettermintEmailEndpoint(): void
    {
        $this->app->bind(EmailEndpoint::class, static function (): EmailEndpoint {
            // A user can configure the project token in the config file or in the services config file.
            $projectToken = config('lettermint.token') ?? config('services.lettermint.token');

            if (! is_string($projectToken)) {
                throw ApiTokenNotFoundException::create();
            }

            return (new LettermintSdk($projectToken))->email;
        });
        $this->app->alias(EmailEndpoint::class, 'lettermint');
    }

    public function provides(): array
    {
        return [
            ...parent::provides(),
            EmailEndpoint::class,
        ];
    }
}
