<?php

use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Lettermint\Exceptions\LettermintConfigException;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\Facades\Lettermint as LettermintFacade;
use Lettermint\Laravel\LettermintServiceProvider;
use Lettermint\Laravel\Tests\Support\RecordingHttpClient;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Lettermint\Lettermint;
use Lettermint\Resources\Domains;
use Lettermint\Resources\Emails;
use Lettermint\Types\SendMailResponse;

beforeEach(function () {
    config()->set('lettermint.token', null);
    config()->set('lettermint.api_token', null);
    config()->set('services.lettermint.token', null);
    config()->set('services.lettermint.api_token', null);
    config()->set('mail.mailers.lettermint', null);
});

/**
 * Route the container client's requests to a recording double.
 */
function fakeLettermintHttp(): RecordingHttpClient
{
    $http = new RecordingHttpClient;
    app()->instance(LettermintServiceProvider::HTTP_CLIENT, $http->client());

    return $http;
}

/**
 * Evaluates config/lettermint.php with the given environment variables set
 * and the other listed ones unset, then restores the environment.
 *
 * @param  list<string>  $names
 * @param  array<string, string>  $env
 * @return array<string, mixed>
 */
function lettermintConfigWithEnv(array $names, array $env): array
{
    $original = [];
    foreach ($names as $name) {
        $original[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
    }
    foreach ($env as $name => $value) {
        putenv("{$name}={$value}");
        $_ENV[$name] = $_SERVER[$name] = $value;
    }

    try {
        return require __DIR__.'/../../config/lettermint.php';
    } finally {
        foreach ($original as $name => [$getenv, $envValue, $serverValue]) {
            $getenv === false ? putenv($name) : putenv("{$name}={$getenv}");
            if ($envValue === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $envValue;
            }
            if ($serverValue === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $serverValue;
            }
        }
    }
}

/**
 * @return array{baseUrl: string, timeout: float, sendingToken: string|null, teamToken: string|null}
 */
function lettermintClientConfig(): array
{
    return app(Lettermint::class)->jsonSerialize();
}

it('registers the lettermint mail transport', function () {
    config()->set('lettermint.token', 'test-token');

    config()->set('mail.mailers.lettermint', [
        'transport' => 'lettermint',
    ]);

    config()->set('mail.default', 'lettermint');

    expect(Mail::getSymfonyTransport())
        ->toBeInstanceOf(LettermintTransportFactory::class);
});

it('binds one shared Lettermint client', function () {
    config()->set('lettermint.token', 'test-token');

    $client = app(Lettermint::class);

    expect($client)->toBeInstanceOf(Lettermint::class)
        ->and(app('lettermint'))->toBe($client)
        ->and(app(Lettermint::class))->toBe($client)
        ->and(LettermintFacade::getFacadeRoot())->toBe($client);
});

it('no longer binds the 2.x endpoint, api client or lettermint.api alias', function () {
    config()->set('lettermint.token', 'test-token');

    expect(app()->bound('Lettermint\\Endpoints\\EmailEndpoint'))->toBeFalse()
        ->and(app()->bound('Lettermint\\Client\\ApiClient'))->toBeFalse()
        ->and(app()->bound('lettermint.api'))->toBeFalse();
});

it('gives the mail transport the shared client', function () {
    config()->set('lettermint.token', 'test-token');

    $transport = app(MailManager::class)->createSymfonyTransport(['transport' => 'lettermint']);

    $property = (new ReflectionClass($transport))->getProperty('lettermint');

    expect($property->getValue($transport))->toBe(app(Lettermint::class));
});

it('throws when no token is configured at all', function () {
    app(Lettermint::class);
})->throws(ApiTokenNotFoundException::class, 'LETTERMINT_PROJECT_TOKEN to send email, LETTERMINT_TEAM_TOKEN to use the Team API');

it('treats empty and blank tokens as not configured', function () {
    config()->set('lettermint.token', '');
    config()->set('lettermint.api_token', '   ');

    app(Lettermint::class);
})->throws(ApiTokenNotFoundException::class);

it('builds a sending-only client from the project token', function () {
    config()->set('lettermint.token', 'lm_project123');

    expect(lettermintClientConfig())->toMatchArray([
        'sendingToken' => '[redacted]',
        'teamToken' => null,
    ]);
});

it('builds a team-only client from the team token', function () {
    config()->set('lettermint.api_token', 'lm_team_abc123');

    expect(lettermintClientConfig())->toMatchArray([
        'sendingToken' => null,
        'teamToken' => '[redacted]',
    ]);
});

it('builds a client with both tokens', function () {
    config()->set('lettermint.token', 'lm_project123');
    config()->set('lettermint.api_token', 'lm_team_abc123');

    expect(lettermintClientConfig())->toMatchArray([
        'sendingToken' => '[redacted]',
        'teamToken' => '[redacted]',
    ]);
});

it('uses the tokens from the services config', function () {
    config()->set('services.lettermint.token', 'lm_fromservices');
    config()->set('services.lettermint.api_token', 'lm_team_fromservices');

    expect(lettermintClientConfig())->toMatchArray([
        'sendingToken' => '[redacted]',
        'teamToken' => '[redacted]',
    ]);
});

it('prefers the lettermint config over the services config', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.token', 'from-lettermint');
    config()->set('services.lettermint.token', 'from-services');
    config()->set('lettermint.api_token', 'team-from-lettermint');
    config()->set('services.lettermint.api_token', 'team-from-services');

    LettermintFacade::emails()->send(['from' => 'a@example.com', 'to' => ['b@example.com'], 'subject' => 'Hi', 'text' => 'Hi']);
    $http->respondWith(['data' => [], 'next_cursor' => null]);
    LettermintFacade::domains()->list();

    expect($http->sent[0]->getHeaderLine('x-lettermint-token'))->toBe('from-lettermint')
        ->and($http->sent[1]->getHeaderLine('Authorization'))->toBe('Bearer team-from-lettermint');
});

it('falls back to the services config when the lettermint config is empty', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.token', '');
    config()->set('services.lettermint.token', 'from-services');

    LettermintFacade::emails()->send(['from' => 'a@example.com', 'to' => ['b@example.com'], 'subject' => 'Hi', 'text' => 'Hi']);

    expect($http->lastRawRequest()->getHeaderLine('x-lettermint-token'))->toBe('from-services');
});

it('reads the team token from LETTERMINT_TEAM_TOKEN and falls back to LETTERMINT_API_TOKEN', function (array $env, ?string $expected) {
    $config = lettermintConfigWithEnv(['LETTERMINT_TEAM_TOKEN', 'LETTERMINT_API_TOKEN'], $env);

    expect($config['api_token'])->toBe($expected);
})->with([
    'team token' => [['LETTERMINT_TEAM_TOKEN' => 'lm_team_new'], 'lm_team_new'],
    '2.x api token' => [['LETTERMINT_API_TOKEN' => 'lm_team_old'], 'lm_team_old'],
    'both, the new name wins' => [['LETTERMINT_TEAM_TOKEN' => 'lm_team_new', 'LETTERMINT_API_TOKEN' => 'lm_team_old'], 'lm_team_new'],
    'neither' => [[], null],
]);

it('reads the project token from LETTERMINT_PROJECT_TOKEN and falls back to LETTERMINT_TOKEN', function (array $env, ?string $expected) {
    $config = lettermintConfigWithEnv(['LETTERMINT_PROJECT_TOKEN', 'LETTERMINT_TOKEN'], $env);

    expect($config['token'])->toBe($expected);
})->with([
    'project token' => [['LETTERMINT_PROJECT_TOKEN' => 'lm_new'], 'lm_new'],
    'legacy token' => [['LETTERMINT_TOKEN' => 'lm_old'], 'lm_old'],
    'both, the new name wins' => [['LETTERMINT_PROJECT_TOKEN' => 'lm_new', 'LETTERMINT_TOKEN' => 'lm_old'], 'lm_new'],
]);

it('passes the configured request timeout to the client', function (mixed $configured, float $expected) {
    config()->set('lettermint.token', 'test-token');
    config()->set('lettermint.timeout', $configured);

    expect(lettermintClientConfig()['timeout'])->toBe($expected);
})->with([
    'integer' => [42, 42.0],
    'float' => [2.5, 2.5],
    'numeric string' => ['30', 30.0],
]);

it('defaults the request timeout to 15 seconds', function () {
    config()->set('lettermint.token', 'test-token');

    expect(lettermintClientConfig()['timeout'])->toBe(15.0);
});

it('sends email through the facade with a sending-only config', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.token', 'lm_project123');
    $http->respondWith(['message_id' => 'msg-1', 'status' => 'pending']);

    $result = LettermintFacade::emails()->send(
        ['from' => 'a@example.com', 'to' => ['b@example.com'], 'subject' => 'Hi', 'text' => 'Hi'],
        idempotencyKey: 'facade-key',
    );

    $request = $http->lastRawRequest();

    expect(LettermintFacade::emails())->toBeInstanceOf(Emails::class)
        ->and($result)->toBeInstanceOf(SendMailResponse::class)
        ->and($result->message_id)->toBe('msg-1')
        ->and((string) $request->getUri())->toBe('https://api.lettermint.co/v1/send')
        ->and($request->getHeaderLine('x-lettermint-token'))->toBe('lm_project123')
        ->and($request->getHeaderLine('Idempotency-Key'))->toBe('facade-key')
        ->and($request->hasHeader('Authorization'))->toBeFalse();
});

it('rejects Team API calls on a sending-only config before any request', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.token', 'lm_project123');

    expect(fn () => LettermintFacade::domains()->list())
        ->toThrow(LettermintConfigException::class, 'domains.list needs teamToken');

    expect($http->sent)->toBe([]);
});

it('uses the Team API through the facade with a team-only config', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.api_token', 'lm_team_abc123');
    $http->respondWith(['data' => [['id' => 'dom-1', 'domain' => 'example.com']], 'next_cursor' => null]);

    $page = LettermintFacade::domains()->list();

    $request = $http->lastRawRequest();

    expect(LettermintFacade::domains())->toBeInstanceOf(Domains::class)
        ->and($page->data[0]->domain)->toBe('example.com')
        ->and((string) $request->getUri())->toBe('https://api.lettermint.co/v1/domains')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer lm_team_abc123')
        ->and($request->hasHeader('x-lettermint-token'))->toBeFalse();
});

it('rejects sending on a team-only config before any request', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.api_token', 'lm_team_abc123');

    expect(fn () => LettermintFacade::emails()->send(['from' => 'a@example.com', 'to' => ['b@example.com'], 'subject' => 'Hi']))
        ->toThrow(LettermintConfigException::class, 'emails.send needs sendingToken');

    expect($http->sent)->toBe([]);
});

it('forwards client methods through the facade', function () {
    $http = fakeLettermintHttp();
    config()->set('lettermint.api_token', 'lm_team_abc123');
    $http->respondWith(new Response(200, ['Content-Type' => 'text/plain'], 'pong'));

    expect(LettermintFacade::ping())->toBe('pong')
        ->and($http->lastRawRequest()->getHeaderLine('Authorization'))->toBe('Bearer lm_team_abc123');
});

it('never exposes the tokens when the client is dumped or encoded', function () {
    config()->set('lettermint.token', 'lm_secretproject');
    config()->set('lettermint.api_token', 'lm_team_secretteam');

    $client = app(Lettermint::class);

    expect(print_r($client, true))->not->toContain('secret')
        ->and(json_encode($client))->not->toContain('secret')
        ->and(fn () => serialize($client))->toThrow(LettermintConfigException::class);
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
    $config = $configProperty->getValue($transport);

    expect($config)->toHaveKey('route_id');
    expect($config['route_id'])->toBe('broadcast');
});
