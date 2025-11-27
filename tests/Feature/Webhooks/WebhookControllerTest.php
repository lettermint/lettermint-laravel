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
