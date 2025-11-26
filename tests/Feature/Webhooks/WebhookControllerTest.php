<?php

use Illuminate\Support\Facades\Event;
use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageHardBounced;
use Lettermint\Laravel\Events\WebhookTest as WebhookTestEvent;

beforeEach(function () {
    config()->set('lettermint.webhooks.secret', 'test-webhook-secret');
    config()->set('lettermint.webhooks.tolerance', 300);
});

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
        ],
    ]);

    $headers = createWebhookSignature($payload, 'test-webhook-secret');

    $response = $this->postJson(
        route('lettermint.webhook'),
        [],
        array_merge($headers, ['Content-Type' => 'application/json']),
    )->setContent($payload);

    // Manually call the route with raw body
    $response = $this->call(
        'POST',
        route('lettermint.webhook'),
        [],
        [],
        [],
        array_merge([
            'HTTP_X_LETTERMINT_SIGNATURE' => $headers['X-Lettermint-Signature'],
            'HTTP_X_LETTERMINT_DELIVERY' => $headers['X-Lettermint-Delivery'],
            'CONTENT_TYPE' => 'application/json',
        ]),
        $payload
    );

    $response->assertStatus(200);
    $response->assertJson(['status' => 'ok']);

    Event::assertDispatched(MessageDelivered::class, function ($event) {
        return $event->payload->id === 'webhook-123'
            && $event->payload->messageId === 'msg-456';
    });
});

it('returns 401 for invalid signature', function () {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [],
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
        'data' => [],
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

it('dispatches correct event for each webhook type', function (string $eventType, string $eventClass) {
    Event::fake();

    $payload = json_encode([
        'id' => 'webhook-123',
        'event' => $eventType,
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [],
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

    Event::assertDispatched($eventClass);
})->with([
    ['message.delivered', MessageDelivered::class],
    ['message.hard_bounced', MessageHardBounced::class],
    ['webhook.test', WebhookTestEvent::class],
]);

it('registers webhook route with default prefix', function () {
    // The default prefix is 'lettermint', resulting in /lettermint/webhook
    $url = route('lettermint.webhook');

    expect($url)->toContain('/lettermint/webhook');
});
