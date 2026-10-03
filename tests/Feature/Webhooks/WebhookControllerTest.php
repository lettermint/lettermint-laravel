<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageHardBounced;
use Lettermint\Laravel\Events\MessageInbound;
use Lettermint\Laravel\Events\SuppressionAdded;
use Lettermint\Laravel\Events\SuppressionRemoved;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;
use Lettermint\Laravel\Events\WebhookTest as WebhookTestEvent;
use Lettermint\Laravel\Webhooks\WebhookEventType;

beforeEach(function () {
    config()->set('lettermint.webhooks.secret', 'test-webhook-secret');
    config()->set('lettermint.webhooks.tolerance', 300);
});

/**
 * @param  array<string, mixed>  $payload
 */
function postSignedWebhook(array $payload): TestResponse
{
    $body = json_encode($payload);
    $headers = createWebhookSignature($body, 'test-webhook-secret');

    return test()->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $body
    );
}

function createWebhookSignature(string $payload, string $secret, ?int $timestamp = null): array
{
    $timestamp = $timestamp ?? time();
    $signedContent = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedContent, $secret);

    return [
        'X-Lettermint-Signature' => "t={$timestamp},v1={$signature}",
        'X-Lettermint-Delivery' => (string) $timestamp,
    ];
}

it('handles a valid webhook and dispatches event', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'recipient' => 'test@example.com',
            'response' => [
                'status_code' => 250,
                'enhanced_status_code' => '2.0.0',
                'content' => 'OK',
            ],
            'metadata' => [],
            'tag' => null,
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);

    Event::assertDispatched(MessageDelivered::class, function ($event) {
        return $event->envelope->id === 'webhook-123'
            && $event->data->messageId === 'msg-456'
            && $event->data->response->statusCode === 250;
    });
});

it('returns 401 for invalid signature', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 250],
            'metadata' => [],
        ],
    ]);

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => 't=1234567890,v1=invalid-signature',
            'HTTP_X_LETTERMINT_DELIVERY' => '1234567890',
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(401);
    $response->assertJson(['error' => 'Invalid signature']);

    Event::assertNotDispatched(LettermintWebhookEvent::class);
});

it('returns 401 for missing signature header', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 250],
            'metadata' => [],
        ],
    ]);

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_DELIVERY' => (string) time(),
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(401);

    Event::assertNotDispatched(LettermintWebhookEvent::class);
});

it('dispatches correct event for message.delivered', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 250],
            'metadata' => [],
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(200);
    Event::assertDispatched(MessageDelivered::class);
});

it('dispatches correct event for message.hard_bounced', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.hard_bounced',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 550],
            'metadata' => [],
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(200);
    Event::assertDispatched(MessageHardBounced::class);
});

it('dispatches correct event for webhook.test', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'webhook.test',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message' => 'Test webhook',
            'webhook_id' => 'webhook-456',
            'timestamp' => 1705315800,
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $response->assertStatus(200);
    Event::assertDispatched(WebhookTestEvent::class);
});

it('registers webhook route with default prefix', function () {
    $url = route('lettermint.webhook');

    expect($url)->toContain('/lettermint/webhook');
});

it('dispatches inbound mail with an attachment that has no filename', function (array $filenameData, string $contentType, string $expectedFilename) {
    Event::fake([MessageInbound::class]);

    $content = "From: sender@example.com\r\nSubject: Attached email\r\n\r\nExample content";
    $payload = json_encode([
        'id' => 'webhook-inbound',
        'event' => 'message.inbound',
        'timestamp' => '2026-09-25T07:47:31Z',
        'data' => [
            'route' => 'support',
            'message_id' => 'inbound-message',
            'from' => ['email' => 'sender@example.com', 'name' => 'Sender'],
            'to' => [['email' => 'help@example.com']],
            'recipient' => 'help@example.com',
            'subject' => 'Re: [T-12345678] Support request',
            'date' => '2026-09-25T07:47:30Z',
            'body' => ['text' => 'Please see the attached email.', 'html' => null],
            'attachments' => [[
                ...$filenameData,
                'content' => base64_encode($content),
                'content_type' => $contentType,
                'size' => strlen($content),
                'content_id' => 'attached-email',
            ]],
        ],
    ]);
    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->call('POST', route('lettermint.webhook'), [], [], [], [
        'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
        'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk()->assertJson(['status' => 'ok']);
    Event::assertDispatched(MessageInbound::class, function (MessageInbound $event) use ($expectedFilename, $contentType, $content): bool {
        $attachment = $event->data->attachments[0];

        return $event->data->body->text === 'Please see the attached email.'
            && $attachment->filename === $expectedFilename
            && $attachment->contentType === $contentType
            && $attachment->getDecodedContent() === $content
            && $attachment->size === strlen($content)
            && $attachment->contentId === 'attached-email';
    });
})->with([
    'null filename' => [['filename' => null], 'message/rfc822', 'attachment.eml'],
    'missing filename' => [[], 'message/rfc822', 'attachment.eml'],
    'another content type' => [['filename' => null], 'application/octet-stream', 'attachment'],
    'original filename' => [['filename' => 'original.eml'], 'message/rfc822', 'original.eml'],
]);

it('handles signed suppression webhooks and dispatches typed events', function (string $eventType, string $eventClass, string $type, string $value, string $appliesTo): void {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => $eventType,
        'timestamp' => '2024-01-15T10:30:00Z',
        'context' => [
            'scope' => 'team',
            'team_id' => 'team-123',
            'project_id' => null,
            'route_id' => null,
        ],
        'data' => [
            'suppression_id' => 'suppression-456',
            'type' => $type,
            'value' => $value,
            'reason' => 'manual',
            'applies_to' => $appliesTo,
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    )->assertOk()->assertJson(['status' => 'ok']);

    Event::assertDispatchedTimes($eventClass, 1);
    Event::assertDispatched($eventClass, function ($event) use ($eventType, $type, $value, $appliesTo): bool {
        return $event->getEnvelope()->id === 'webhook-123'
            && $event->getEnvelope()->event === WebhookEventType::from($eventType)
            && $event->getEnvelope()->timestamp->format('c') === '2024-01-15T10:30:00+00:00'
            && $event->data->suppressionId === 'suppression-456'
            && $event->data->type === $type
            && $event->data->value === $value
            && $event->data->reason === 'manual'
            && $event->data->appliesTo === $appliesTo;
    });
})->with([
    'added' => ['suppression.added', SuppressionAdded::class],
    'removed' => ['suppression.removed', SuppressionRemoved::class],
])->with([
    'email' => ['email', 'test@example.com'],
    'domain' => ['domain', 'example.com'],
    'extension' => ['extension', 'com'],
])->with(['all', 'broadcast']);

it('acknowledges unknown webhook event types and dispatches a generic event with the raw payload', function (array $envelope) {
    // Record every package event through the real dispatcher.
    $dispatched = [];
    Event::listen('Lettermint\\Laravel\\Events\\*', function (string $name, array $data) use (&$dispatched) {
        $dispatched[] = $data[0];
    });

    $payload = [
        'id' => 'webhook-123',
        ...$envelope,
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'something_new' => ['nested' => true],
        ],
    ];

    postSignedWebhook($payload)
        ->assertOk()
        ->assertJson(['status' => 'ok']);

    expect($dispatched)->toHaveCount(1);
    expect($dispatched[0])->toBeInstanceOf(UnknownWebhookEventReceived::class);
    expect($dispatched[0]->event)->toBe(is_string($envelope['event'] ?? null) ? $envelope['event'] : '');
    expect($dispatched[0]->payload)->toBe($payload);
})->with([
    'new event type' => [['event' => 'message.some_future_event']],
    'new event namespace' => [['event' => 'domain.verified']],
    'differently cased known type' => [['event' => 'MESSAGE.DELIVERED']],
    'missing event name' => [[]],
    'non-string event name' => [['event' => ['message.delivered']]],
]);

it('does not dispatch the generic unknown event for known event types', function () {
    Event::fake();

    postSignedWebhook([
        'id' => 'webhook-123',
        'event' => 'webhook.test',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message' => 'Test webhook',
            'webhook_id' => 'wh-789',
            'timestamp' => 1705315800,
        ],
    ])->assertOk()->assertJson(['status' => 'ok']);

    Event::assertDispatchedTimes(WebhookTestEvent::class, 1);
    Event::assertNotDispatched(UnknownWebhookEventReceived::class);
});
