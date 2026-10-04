<?php

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Mail\MailManager;
use Lettermint\Exceptions\ConnectionException;
use Lettermint\Exceptions\RateLimitException;
use Lettermint\Exceptions\ServerException;
use Lettermint\Exceptions\UnexpectedResponseException;
use Lettermint\Exceptions\ValidationException;
use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;
use Lettermint\Laravel\LettermintServiceProvider;
use Lettermint\Laravel\Tests\Support\RecordingHttpClient;
use Lettermint\Laravel\Transport\LettermintTransportFactory;
use Lettermint\Lettermint;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Part\DataPart;

/*
 * These tests drive a real Lettermint\Lettermint client backed by an
 * HTTP-level recording double, so they assert the exact JSON body and request
 * headers that reach the Lettermint API.
 */

beforeEach(function () {
    config()->set('services.lettermint.token', 'test-token');

    $this->http = new RecordingHttpClient;
    $this->lettermint = new Lettermint(sendingToken: 'test-token', httpClient: $this->http->client());
    $this->transport = new LettermintTransportFactory($this->lettermint);
});

/**
 * The payload keys the transport always sends, in the order it sends them.
 * Keys in $overrides replace the defaults in place; new keys are appended.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function lettermintPayload(array $overrides = []): array
{
    return array_replace([
        'headers' => [],
        'from' => 'from@example.com',
        'to' => ['to@example.com'],
        'subject' => 'Hello world!',
        'html' => null,
        'text' => 'This is a Lettermint test mail.',
        'cc' => [],
        'bcc' => [],
        'reply_to' => [],
    ], $overrides);
}

/**
 * An email that carries every piece of per-message state: tag, metadata,
 * a custom idempotency key and an attachment.
 */
function lettermintStatefulEmail(): Email
{
    $email = lettermintTestEmail()->subject('Stateful email');
    $email->getHeaders()->addTextHeader('Idempotency-Key', 'first-message-key');
    $email->getHeaders()->add(new TagHeader('first-tag'));
    $email->getHeaders()->add(new MetadataHeader('first', 'metadata'));
    $email->attach('first attachment', 'first.txt', 'text/plain');

    return $email;
}

/**
 * An attachment whose MIME headers carry no Content-Disposition, so Symfony
 * reports its filename as null rather than an empty string.
 */
function lettermintAttachmentWithoutDisposition(): DataPart
{
    return new class('no disposition', null, 'text/plain') extends DataPart
    {
        public function getPreparedHeaders(): Headers
        {
            $headers = parent::getPreparedHeaders();
            $headers->remove('Content-Disposition');

            return $headers;
        }
    };
}

function lettermintTestEmail(): Email
{
    return (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');
}

it('creates a transport instance', function () {
    $transport = new LettermintTransportFactory($this->lettermint);

    expect($transport)->toBeInstanceOf(LettermintTransportFactory::class);
});

it('registers the lettermint transport', function () {
    $app = app();

    $app['config']->set('lettermint', [
        'token' => 'test_token_12345',
    ]);

    $manager = $app->get(MailManager::class);

    $transport = $manager->createSymfonyTransport(['transport' => 'lettermint']);

    expect((string) $transport)->toBe('lettermint');
});

it('uses route_id from mailer config via mail manager', function () {
    $app = app();

    $app['config']->set('lettermint', [
        'token' => 'test_token_12345',
    ]);

    $manager = $app->get(MailManager::class);

    $transport = $manager->createSymfonyTransport([
        'transport' => 'lettermint',
        'route_id' => 'broadcast',
    ]);

    expect((string) $transport)->toBe('lettermint');

    $reflection = new ReflectionClass($transport);
    $configProperty = $reflection->getProperty('config');
    $configProperty->setAccessible(true);
    $config = $configProperty->getValue($transport);

    expect($config)->toHaveKey('route_id');
    expect($config['route_id'])->toBe('broadcast');
});

it('sends a plain email', function () {
    $this->transport->send(lettermintTestEmail());

    expect($this->http->requests)->toHaveCount(1);
    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload(),
        'headers' => [],
    ]);
});

it('sends cc, bcc, reply-to and both bodies', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply-to@example.com')
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.')
        ->html('<p>Test HTML body</p>');

    $this->transport->send($email);

    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload([
            'to' => ['"Acme" <to@example.com>'],
            'html' => '<p>Test HTML body</p>',
            'cc' => ['cc@example.com'],
            'bcc' => ['bcc@example.com'],
            'reply_to' => ['reply-to@example.com'],
        ]),
        'headers' => [],
    ]);
});

it('sends to multiple recipients and multiple cc, bcc and reply-to addresses', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(
            new Address('to@example.com', 'Acme'),
            new Address('sales@example.com', 'Acme Sales')
        )
        ->cc('cc1@example.com', new Address('cc2@example.com', 'CC Two'))
        ->bcc('bcc1@example.com', 'bcc2@example.com')
        ->replyTo('reply1@example.com', 'reply2@example.com')
        ->subject('Hello world!')
        ->text('This is a Lettermint test mail.');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'to' => ['"Acme" <to@example.com>', '"Acme Sales" <sales@example.com>'],
        'cc' => ['cc1@example.com', '"CC Two" <cc2@example.com>'],
        'bcc' => ['bcc1@example.com', 'bcc2@example.com'],
        'reply_to' => ['reply1@example.com', 'reply2@example.com'],
    ]));
});

it('sends the sender address including its display name', function () {
    $email = lettermintTestEmail()->from(new Address('from@example.com', 'Acme Sender'));

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'from' => '"Acme Sender" <from@example.com>',
    ]));
});

it('sends an html-only email with a null text body', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Hello world!')
        ->html('<p>Only HTML</p>');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'html' => '<p>Only HTML</p>',
        'text' => null,
    ]));
});

it('sends custom headers and drops address, subject and transport-control headers', function () {
    $email = lettermintTestEmail()
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply-to@example.com')
        ->sender('sender@example.com');

    $email->getHeaders()->addTextHeader('X-Custom-Header', 'test-value');
    $email->getHeaders()->addTextHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');
    $email->getHeaders()->addTextHeader('Idempotency-Key', 'custom-key-123');
    $email->getHeaders()->addTextHeader('X-LM-Tag', 'legacy-tag');

    $this->transport->send($email);

    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload([
            'headers' => [
                'X-Custom-Header' => 'test-value',
                'List-Unsubscribe' => '<https://example.com/unsubscribe>',
            ],
            // The envelope sender (Sender header) is what is sent as "from".
            'from' => 'sender@example.com',
            'cc' => ['cc@example.com'],
            'bcc' => ['bcc@example.com'],
            'reply_to' => ['reply-to@example.com'],
            'tag' => 'legacy-tag',
        ]),
        'headers' => ['Idempotency-Key' => 'custom-key-123'],
    ]);
});

it('sends attachments as base64 without line breaks', function () {
    $email = lettermintTestEmail();

    $binary = random_bytes(200); // Long enough for MIME base64 to wrap lines
    $email->attach('base64', 'test.txt', 'text/plain');
    $email->attach($binary, 'image.png', 'image/png');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'attachments' => [
            [
                'filename' => 'test.txt',
                'content' => base64_encode('base64'),
                'content_type' => 'text/plain',
            ],
            [
                'filename' => 'image.png',
                'content' => base64_encode($binary),
                'content_type' => 'image/png',
            ],
        ],
    ]));
});

it('forwards attachment content_type parameters to the API (calendar RSVP)', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->subject('Interview invitation')
        ->text('See attached invitation.');

    $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nMETHOD:REQUEST\r\nEND:VCALENDAR\r\n";
    $email->attach($ics, 'invite.ics', 'text/calendar; method=REQUEST');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'subject' => 'Interview invitation',
        'text' => 'See attached invitation.',
        'attachments' => [
            [
                'filename' => 'invite.ics',
                'content' => base64_encode($ics),
                'content_type' => 'text/calendar; method=REQUEST',
            ],
        ],
    ]));
});

it('sends inline attachments with their content id', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to(new Address('to@example.com', 'Acme'))
        ->subject('Hello world!')
        ->html('<img src="cid:logo@example.com">');

    $image = new DataPart('image-data', 'logo.png', 'image/png');
    $image->asInline();
    $image->setContentId('logo@example.com');
    $email->addPart($image);

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'to' => ['"Acme" <to@example.com>'],
        'html' => '<img src="cid:logo@example.com">',
        'text' => null,
        'attachments' => [
            [
                'filename' => 'logo.png',
                'content' => base64_encode('image-data'),
                'content_type' => 'image/png',
                'content_id' => 'logo@example.com',
            ],
        ],
    ]));
});

it('sends the configured route_id', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['route_id' => 'test-route-123']);

    $transport->send(lettermintTestEmail());

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'route' => 'test-route-123',
    ]));
});

it('does not send a route when route_id is not set or empty', function (array $config) {
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $transport->send(lettermintTestEmail());

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload());
})->with([
    'not set' => [[]],
    'null' => [['route_id' => null]],
    'empty string' => [['route_id' => '']],
]);

it('sends a tag set with TagHeader', function () {
    $email = lettermintTestEmail();
    $email->getHeaders()->add(new TagHeader('welcome-email'));

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'headers' => ['X-Tag' => 'welcome-email'],
        'tag' => 'welcome-email',
    ]));
});

it('sends metadata set with MetadataHeader', function () {
    $email = lettermintTestEmail();
    $email->getHeaders()->add(new MetadataHeader('user_id', '12345'));
    $email->getHeaders()->add(new MetadataHeader('campaign', 'summer-sale'));

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'headers' => [
            'X-Metadata-user_id' => '12345',
            'X-Metadata-campaign' => 'summer-sale',
        ],
        'metadata' => ['user_id' => '12345', 'campaign' => 'summer-sale'],
    ]));
});

it('sends a tag set with the X-LM-Tag header for backward compatibility', function () {
    $email = lettermintTestEmail();
    $email->getHeaders()->addHeader('X-LM-Tag', 'tti-test');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'tag' => 'tti-test',
    ]));
});

it('prefers TagHeader over X-LM-Tag when both are present', function () {
    $email = lettermintTestEmail();
    $email->getHeaders()->add(new TagHeader('primary-tag'));
    $email->getHeaders()->addHeader('X-LM-Tag', 'fallback-tag');

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'headers' => ['X-Tag' => 'primary-tag'],
        'tag' => 'primary-tag',
    ]));
});

it('sends both tag and metadata', function () {
    $email = lettermintTestEmail();
    $email->getHeaders()->add(new TagHeader('user-notification'));
    $email->getHeaders()->add(new MetadataHeader('user_id', '67890'));
    $email->getHeaders()->add(new MetadataHeader('notification_type', 'password_reset'));

    $this->transport->send($email);

    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'headers' => [
            'X-Tag' => 'user-notification',
            'X-Metadata-user_id' => '67890',
            'X-Metadata-notification_type' => 'password_reset',
        ],
        'tag' => 'user-notification',
        'metadata' => ['user_id' => '67890', 'notification_type' => 'password_reset'],
    ]));
});

it('omits tag and metadata when none are provided', function () {
    $this->transport->send(lettermintTestEmail());

    expect($this->http->lastRequest()['data'])
        ->not->toHaveKey('tag')
        ->not->toHaveKey('metadata');
});

it('sends a custom Idempotency-Key header as request header, not as email header', function (array $config) {
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $email = lettermintTestEmail();
    $email->getHeaders()->addHeader('Idempotency-Key', 'custom-key-123');
    $email->getHeaders()->addHeader('X-Custom-Header', 'test-value');

    $transport->send($email);

    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload([
            'headers' => ['X-Custom-Header' => 'test-value'],
        ]),
        'headers' => ['Idempotency-Key' => 'custom-key-123'],
    ]);
})->with([
    'default config' => [[]],
    'automatic idempotency disabled' => [['idempotency' => false]],
    'automatic idempotency enabled' => [['idempotency' => true]],
    'automatic idempotency with short window' => [['idempotency' => true, 'idempotency_window' => 300]],
]);

it('does not send an Idempotency-Key when automatic idempotency is disabled', function (array $config) {
    $transport = new LettermintTransportFactory($this->lettermint, $config);

    $transport->send(lettermintTestEmail());

    expect($this->http->lastRequest()['headers'])->toBe([]);
})->with([
    'default config' => [[]],
    'explicitly disabled' => [['idempotency' => false]],
    'truthy but not true' => [['idempotency' => 1]],
]);

it('generates a content-based idempotency key with the default 24-hour window', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['idempotency' => true]);

    $email = lettermintTestEmail()
        ->to(new Address('to@example.com', 'Acme'))
        ->cc('cc@example.com')
        ->html('<p>Test HTML body</p>');

    $transport->send($email);

    $expectedKey = hash('sha256', implode('|', [
        'Hello world!',
        '"Acme" <to@example.com>',
        'cc@example.com',
        '<p>Test HTML body</p>',
        'from@example.com',
    ]));

    expect($this->http->lastRequest()['headers'])->toBe(['Idempotency-Key' => $expectedKey]);
    expect($this->http->lastRequest()['data'])->toBe(lettermintPayload([
        'to' => ['"Acme" <to@example.com>'],
        'html' => '<p>Test HTML body</p>',
        'cc' => ['cc@example.com'],
    ]));
});

it('generates a time-bucketed idempotency key with a custom window', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['idempotency' => true, 'idempotency_window' => 3600]);

    $keyFor = fn (int $time) => hash('sha256', implode('|', [
        'Hello world!',
        'to@example.com',
        'This is a Lettermint test mail.',
        'from@example.com',
        floor($time / 3600),
    ]));

    $before = time();
    $transport->send(lettermintTestEmail());
    $after = time();

    expect($this->http->lastRequest()['headers']['Idempotency-Key'] ?? null)
        ->toBeIn(array_unique([$keyFor($before), $keyFor($after)]));
});

it('generates the same idempotency key for identical content and a different key for different content', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['idempotency' => true]);

    $transport->send(lettermintTestEmail());
    $transport->send(lettermintTestEmail());
    $transport->send(lettermintTestEmail()->subject('Another subject'));

    $keys = array_map(fn (array $request) => $request['headers']['Idempotency-Key'], $this->http->requests);

    expect($keys[0])->toBe($keys[1]);
    expect($keys[2])->not->toBe($keys[0]);
});

it('sends every supported feature in a single payload', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['route_id' => 'transactional']);

    $email = (new Email)
        ->from(new Address('from@example.com', 'Acme'))
        ->to('to@example.com')
        ->cc('cc@example.com')
        ->bcc('bcc@example.com')
        ->replyTo('reply-to@example.com')
        ->subject('Your invoice')
        ->text('Invoice attached.')
        ->html('<p>Invoice attached.</p><img src="cid:logo@example.com">');

    $email->getHeaders()->addTextHeader('X-Custom-Header', 'test-value');
    $email->getHeaders()->addTextHeader('Idempotency-Key', 'invoice-42');
    $email->getHeaders()->add(new TagHeader('invoices'));
    $email->getHeaders()->add(new MetadataHeader('invoice_id', '42'));

    $email->attach('%PDF-1.4', 'invoice.pdf', 'application/pdf');
    $logo = (new DataPart('image-data', 'logo.png', 'image/png'))->asInline()->setContentId('logo@example.com');
    $email->addPart($logo);

    $transport->send($email);

    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => [
            'headers' => [
                'X-Custom-Header' => 'test-value',
                'X-Tag' => 'invoices',
                'X-Metadata-invoice_id' => '42',
            ],
            'from' => '"Acme" <from@example.com>',
            'to' => ['to@example.com'],
            'subject' => 'Your invoice',
            'html' => '<p>Invoice attached.</p><img src="cid:logo@example.com">',
            'text' => 'Invoice attached.',
            'cc' => ['cc@example.com'],
            'bcc' => ['bcc@example.com'],
            'reply_to' => ['reply-to@example.com'],
            'route' => 'transactional',
            'tag' => 'invoices',
            'metadata' => ['invoice_id' => '42'],
            'attachments' => [
                [
                    'filename' => 'invoice.pdf',
                    'content' => base64_encode('%PDF-1.4'),
                    'content_type' => 'application/pdf',
                ],
                [
                    'filename' => 'logo.png',
                    'content' => base64_encode('image-data'),
                    'content_type' => 'image/png',
                    'content_id' => 'logo@example.com',
                ],
            ],
        ],
        'headers' => ['Idempotency-Key' => 'invoice-42'],
    ]);
});

it('throws a transport exception with the HTTP status when the API rejects the email', function () {
    $this->http->respondWithError(422, [
        'message' => 'The from field must be a verified domain.',
        'errors' => ['from' => ['The from field must be a verified domain.']],
    ]);

    try {
        $this->transport->send(lettermintTestEmail());
        $this->fail('Expected a TransportException.');
    } catch (TransportException $exception) {
        expect($exception->getMessage())->toBe('Sending email via Lettermint API failed: The from field must be a verified domain.')
            ->and($exception->getCode())->toBe(422)
            ->and($exception->getPrevious())->toBeInstanceOf(ValidationException::class)
            ->and($exception->getPrevious()->errors)->toBe(['from' => ['The from field must be a verified domain.']]);
    }
});

it('maps every SDK failure to a transport exception with the HTTP status as its code', function (Closure $respond, string $previous, int $code) {
    $respond($this->http);

    try {
        $this->transport->send(lettermintTestEmail());
        $this->fail('Expected a TransportException.');
    } catch (TransportException $exception) {
        expect($exception->getMessage())->toStartWith('Sending email via Lettermint API failed: ')
            ->and($exception->getCode())->toBe($code)
            ->and($exception->getPrevious())->toBeInstanceOf($previous);
    }
})->with([
    'rate limited' => [fn (RecordingHttpClient $http) => $http->respondWithError(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '30']), RateLimitException::class, 429],
    'server error' => [fn (RecordingHttpClient $http) => $http->respondWithError(503, ['message' => 'Service Unavailable']), ServerException::class, 503],
    'html error page' => [fn (RecordingHttpClient $http) => $http->respondWith(new Response(502, ['Content-Type' => 'text/html'], '<html>Bad Gateway</html>')), UnexpectedResponseException::class, 502],
    'connection failure' => [fn (RecordingHttpClient $http) => $http->respondWith(new ConnectException('Connection refused', new Request('POST', 'https://api.lettermint.co/v1/send'))), ConnectionException::class, 0],
]);

it('authenticates with the project token and never sends a team token', function () {
    $this->transport->send(lettermintTestEmail());

    $request = $this->http->lastRawRequest();

    expect($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://api.lettermint.co/v1/send')
        ->and($request->getHeaderLine('x-lettermint-token'))->toBe('test-token')
        ->and($request->hasHeader('Authorization'))->toBeFalse();
});

it('sends through the container client and the configured mailer with the same payload', function () {
    $http = new RecordingHttpClient;
    app()->instance(LettermintServiceProvider::HTTP_CLIENT, $http->client());
    config()->set('lettermint.token', 'container-token');
    config()->set('mail.mailers.lettermint_routed', ['transport' => 'lettermint', 'route_id' => 'transactional', 'idempotency' => true]);

    app(MailManager::class)->mailer('lettermint_routed')->getSymfonyTransport()->send(lettermintTestEmail());

    expect($http->requests)->toHaveCount(1)
        ->and($http->lastRequest()['data'])->toBe(lettermintPayload(['route' => 'transactional']))
        ->and($http->lastRequest()['headers'])->toHaveKey('Idempotency-Key')
        ->and($http->lastRawRequest()->getHeaderLine('x-lettermint-token'))->toBe('container-token');
});

it('refuses to create the mailer without a project token', function () {
    config()->set('lettermint.token', null);
    config()->set('services.lettermint.token', null);
    config()->set('lettermint.api_token', 'lm_team_abc123');

    app(MailManager::class)->createSymfonyTransport(['transport' => 'lettermint']);
})->throws(ApiTokenNotFoundException::class, 'LETTERMINT_PROJECT_TOKEN');

it('sets the Message-ID from the Lettermint API response', function () {
    $this->http->respondWith(['message_id' => 'lettermint-message-id-12345', 'status' => 'pending']);

    $sentMessage = $this->transport->send(lettermintTestEmail());

    // The ID has no @, so it is formatted as id@lmta.net to comply with RFC 5322.
    expect($sentMessage->getMessageId())->toBe('lettermint-message-id-12345@lmta.net');
});

it('keeps a Message-ID from the Lettermint API response that already contains @', function () {
    $this->http->respondWith(['message_id' => 'abc@lettermint.co', 'status' => 'pending']);

    $sentMessage = $this->transport->send(lettermintTestEmail());

    expect($sentMessage->getMessageId())->toBe('abc@lettermint.co');
});

it('rejects an attachment without a filename before calling the API', function (Closure $attach) {
    $email = lettermintTestEmail();
    $attach($email);

    expect(fn () => $this->transport->send($email))
        ->toThrow(TransportException::class, 'Lettermint requires every attachment to have a filename');

    expect($this->http->requests)->toBe([]);
})->with([
    'empty filename' => [fn (Email $email) => $email->attach('nameless')],
    'null filename' => [fn (Email $email) => $email->addPart(lettermintAttachmentWithoutDisposition())],
    'nameless inline part' => [fn (Email $email) => $email->addPart((new DataPart('image-data', null, 'image/png'))->asInline())],
]);

it('rejects an email without a subject before calling the API', function () {
    $email = (new Email)
        ->from('from@example.com')
        ->to('to@example.com')
        ->text('This is a Lettermint test mail.');

    expect(fn () => $this->transport->send($email))
        ->toThrow(TransportException::class, 'Lettermint requires a subject');

    expect($this->http->requests)->toBe([]);
});

it('does not leak state from a message that failed mid-build into the next message', function (Closure $breakFirstMessage) {
    // One long-lived transport and endpoint, as in a queue worker.
    $first = lettermintStatefulEmail();
    $breakFirstMessage($first);

    try {
        $this->transport->send($first);
    } catch (Throwable) {
        // The first message is expected to fail; only the next one matters here.
    }

    $this->transport->send(lettermintTestEmail());

    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload(),
        'headers' => [],
    ]);
})->with([
    'attachment with null filename' => [fn (Email $email) => $email->addPart(lettermintAttachmentWithoutDisposition())],
    'attachment with empty filename' => [fn (Email $email) => $email->attach('nameless')],
    'missing subject' => [fn (Email $email) => $email->getHeaders()->remove('Subject')],
]);

it('does not leak state from a message the API rejected into the next message', function () {
    $this->http->respondWithError(422, ['message' => 'Validation failed']);

    expect(fn () => $this->transport->send(lettermintStatefulEmail()))
        ->toThrow(TransportException::class, 'Validation failed');

    $this->transport->send(lettermintTestEmail());

    expect($this->http->requests)->toHaveCount(2);
    expect($this->http->lastRequest())->toBe([
        'path' => '/v1/send',
        'data' => lettermintPayload(),
        'headers' => [],
    ]);
});

it('does not reuse the previous message idempotency key', function () {
    $transport = new LettermintTransportFactory($this->lettermint, ['idempotency' => true]);

    $transport->send(lettermintStatefulEmail());
    $transport->send(lettermintTestEmail());

    [$first, $second] = $this->http->requests;

    expect($first['headers'])->toBe(['Idempotency-Key' => 'first-message-key']);
    expect($second['headers']['Idempotency-Key'])
        ->toBeString()
        ->not->toBe('first-message-key');
});
