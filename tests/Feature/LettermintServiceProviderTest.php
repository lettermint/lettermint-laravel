<?php

use Illuminate\Support\Facades\Mail;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Transport\LettermintTransportFactory;

beforeEach(function () {
    config()->set('lettermint.token', null);
    config()->set('services.lettermint.token', null);
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

it('provides the lettermint singleton', function () {
    config()->set('lettermint.token', 'test-token');

    expect(app()->bound('lettermint'))->toBeTrue()
        ->and(app()->bound(\Lettermint\Lettermint::class))->toBeTrue();
});

it('throws exception when no API token is configured', function () {
    app()->get(\Lettermint\Lettermint::class);
})->throws(ApiTokenNotFoundException::class);

it('uses token from lettermint config', function () {
    config()->set('lettermint.token', 'test-token-from-lettermint');

    $lettermint = app()->get(\Lettermint\Lettermint::class);

    expect($lettermint)->toBeInstanceOf(\Lettermint\Lettermint::class);
});

it('uses token from services config', function () {
    config()->set('services.lettermint.token', 'test-token-from-services');

    $lettermint = app()->get(\Lettermint\Lettermint::class);

    expect($lettermint)->toBeInstanceOf(\Lettermint\Lettermint::class);
});

it('prefers lettermint config token over services config token', function () {
    config()->set('lettermint.token', 'test-token-from-lettermint');
    config()->set('services.lettermint.token', 'test-token-from-services');

    $lettermint = app()->get(\Lettermint\Lettermint::class);

    expect($lettermint)->toBeInstanceOf(\Lettermint\Lettermint::class);
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

    $manager = app(\Illuminate\Mail\MailManager::class);

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
