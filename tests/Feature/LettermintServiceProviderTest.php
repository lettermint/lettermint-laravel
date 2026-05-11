<?php

use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Lettermint\Client\ApiClient;
use Lettermint\Endpoints\EmailEndpoint;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Exceptions\TeamApiTokenNotFoundException;
use Lettermint\Laravel\Facades\Lettermint as LettermintFacade;
use Lettermint\Laravel\Transport\LettermintTransportFactory;

beforeEach(function () {
    config()->set('lettermint.token', null);
    config()->set('lettermint.api_token', null);
    config()->set('services.lettermint.token', null);
    config()->set('services.lettermint.api_token', null);
    config()->set('mail.mailers.lettermint', null);
});

it('registers the lettermint mail transport', function () {
    config()->set('lettermint.token', 'test-token');

    Mail::extend('lettermint', function () {
        return app(LettermintTransportFactory::class);
    });

    config()->set('mail.mailers.lettermint', [
        'transport' => 'lettermint',
    ]);

    config()->set('mail.default', 'lettermint');

    expect(Mail::getSymfonyTransport())
        ->toBeInstanceOf(LettermintTransportFactory::class);
});

it('provides the lettermint email endpoint binding', function () {
    config()->set('lettermint.token', 'test-token');

    expect(app()->bound('lettermint'))->toBeTrue()
        ->and(app()->bound(EmailEndpoint::class))->toBeTrue();
});

it('resolves the facade to the email endpoint', function () {
    config()->set('lettermint.token', 'test-token');

    expect(LettermintFacade::getFacadeRoot())->toBeInstanceOf(EmailEndpoint::class);
});

it('throws exception when no project token is configured', function () {
    app()->get(EmailEndpoint::class);
})->throws(ApiTokenNotFoundException::class);

it('uses project token from lettermint config', function () {
    config()->set('lettermint.token', 'test-token-from-lettermint');

    $email = app()->get(EmailEndpoint::class);

    expect($email)->toBeInstanceOf(EmailEndpoint::class);
});

it('uses project token from services config', function () {
    config()->set('services.lettermint.token', 'test-token-from-services');

    $email = app()->get(EmailEndpoint::class);

    expect($email)->toBeInstanceOf(EmailEndpoint::class);
});

it('uses legacy token from lettermint config', function () {
    config()->set('lettermint.token', 'legacy-test-token');

    $email = app()->get(EmailEndpoint::class);

    expect($email)->toBeInstanceOf(EmailEndpoint::class);
});

it('provides the lettermint api client binding', function () {
    config()->set('lettermint.api_token', 'team-api-token');

    expect(app()->bound('lettermint.api'))->toBeTrue()
        ->and(app()->bound(ApiClient::class))->toBeTrue()
        ->and(app()->get(ApiClient::class))->toBeInstanceOf(ApiClient::class)
        ->and(app()->get('lettermint.api'))->toBeInstanceOf(ApiClient::class);
});

it('uses api token from services config', function () {
    config()->set('services.lettermint.api_token', 'team-api-token-from-services');

    expect(app()->get(ApiClient::class))->toBeInstanceOf(ApiClient::class);
});

it('prefers lettermint api token over services api token', function () {
    config()->set('lettermint.api_token', 'team-api-token-from-lettermint');
    config()->set('services.lettermint.api_token', 'team-api-token-from-services');

    expect(app()->get(ApiClient::class))->toBeInstanceOf(ApiClient::class);
});

it('throws exception when no api token is configured', function () {
    app()->get(ApiClient::class);
})->throws(TeamApiTokenNotFoundException::class);

it('prefers lettermint config token over services config token', function () {
    config()->set('lettermint.token', 'test-token-from-lettermint');
    config()->set('services.lettermint.token', 'test-token-from-services');

    $email = app()->get(EmailEndpoint::class);

    expect($email)->toBeInstanceOf(EmailEndpoint::class);
});

it('has config file', function () {
    expect(config()->has('lettermint'))->toBeTrue();
});

it('passes route_id configuration to transport when using full mail config', function () {
    config([
        'lettermint.token' => 'test-token',
        'mail.mailers.lettermint_broadcast' => [
            'transport' => 'lettermint',
            'route_id' => 'broadcast',
        ],
    ]);

    $manager = app(MailManager::class);

    // When Laravel's mail manager creates a mailer, it should pass the full config
    $mailer = $manager->mailer('lettermint_broadcast');
    $transport = $mailer->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(LettermintTransportFactory::class);

    // Use reflection to check if route_id was passed to the transport
    $reflection = new ReflectionClass($transport);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($transport);

    expect($config)->toHaveKey('route_id');
    expect($config['route_id'])->toBe('broadcast');
});
