<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Lettermint\Laravel\Contracts\WebhookEvent;
use Lettermint\Laravel\Events\MessageClicked;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;
use Lettermint\Laravel\Webhooks\WebhookEventType;

/*
 * End-to-end: deliveries are built and signed the way the Lettermint backend
 * does it (WebhookService::send/generateSignature), then posted to the
 * package's registered route through the full middleware stack.
 */

const BACKEND_WEBHOOK_SECRET = 'whsec_Q2hhbmdlTWVJbkFSZWFsU2VjcmV0MTIzNDU2';

beforeEach(function () {
    config()->set('lettermint.webhooks.secret', BACKEND_WEBHOOK_SECRET);
    config()->set('lettermint.webhooks.tolerance', 300);
});

/**
 * Builds the payload the way the backend does: the transformer output
 * (event, timestamp, sandbox flags, data), then the context, then the
 * delivery id prepended.
 *
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function backendWebhookPayload(string $event, array $data, string $deliveryId = '9d3f6a1e-7b2c-4f1a-9c3e-2b1a0f9e8d7c'): array
{
    $payload = [
        'event' => $event,
        'timestamp' => '2026-10-04T08:15:30.000000Z',
        'sandbox' => false,
        'sandbox_result' => null,
        'data' => $data,
    ];
    $payload['context'] ??= ['scope' => 'project', 'team_id' => 'team-1', 'project_id' => 'project-1', 'route_id' => null];

    return array_merge(['id' => $deliveryId], $payload);
}

/**
 * Signs and posts a payload like the backend: the body is encoded with
 * unescaped slashes and unicode, signed as "<t>.<body>" with HMAC-SHA256
 * keyed by the secret as is (whsec_ prefix included), and the delivery header
 * carries the same timestamp.
 *
 * @param  array<string, mixed>  $payload
 */
function postBackendWebhook(array $payload, ?int $timestamp = null, ?string $body = null): TestResponse
{
    $timestamp ??= time();
    $signedBody = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $signature = hash_hmac('sha256', $timestamp.'.'.$signedBody, BACKEND_WEBHOOK_SECRET);

    return test()->call('POST', route('lettermint.webhook'), [], [], [], [
        'HTTP_USER_AGENT' => 'Lettermint/1.0',
        'HTTP_X_LETTERMINT_SIGNATURE' => 't='.$timestamp.',v1='.$signature,
        'HTTP_X_LETTERMINT_EVENT' => $payload['event'],
        'HTTP_X_LETTERMINT_DELIVERY' => (string) $timestamp,
        'HTTP_X_LETTERMINT_ATTEMPT' => '1',
        'CONTENT_TYPE' => 'application/json',
    ], $body ?? $signedBody);
}

/**
 * @return array<string, mixed>
 */
function backendClickedData(): array
{
    return [
        'message_id' => '0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b',
        'subject' => 'Your order ✓ shipped',
        'metadata' => ['order_id' => '1234'],
        'tag' => 'orders',
        'tags' => [['name' => 'campaign', 'value' => 'autumn']],
        'recipient' => 'jane@example.com',
        'clicked_at' => '2026-10-04T08:15:29+00:00',
        'destination_url' => 'https://shop.example.com/orders/1234?ref=mail',
        'link_index' => 2,
        'anchor_text' => 'Bekijk je bestelling →',
        'first_click' => true,
        'device_type' => 'mobile',
        'client_type' => 'browser',
        'client_name' => 'Safari',
        'user_agent' => 'Mozilla/5.0 (iPhone)',
        'bot' => [
            'detected' => false,
            'probability' => 0.02,
            'classification' => 'genuine',
            'proxy_type' => null,
            'reason_codes' => [],
            'machine' => false,
            'counts_for_metrics' => true,
            'counts_for_status' => true,
            'webhook_eligible' => true,
        ],
    ];
}

it('verifies a backend-signed delivery and dispatches the typed event', function () {
    Event::fake([MessageClicked::class]);
    $payload = backendWebhookPayload('message.clicked', backendClickedData());

    postBackendWebhook($payload)->assertOk()->assertExactJson(['status' => 'ok']);

    Event::assertDispatchedTimes(MessageClicked::class, 1);
    Event::assertDispatched(MessageClicked::class, function (MessageClicked $event) use ($payload): bool {
        expect($event->envelope->id)->toBe('9d3f6a1e-7b2c-4f1a-9c3e-2b1a0f9e8d7c')
            ->and($event->envelope->event)->toBe(WebhookEventType::MessageClicked)
            ->and($event->envelope->timestamp?->format('Y-m-d H:i:s'))->toBe('2026-10-04 08:15:30')
            ->and($event->data->messageId)->toBe('0199a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b')
            ->and($event->data->subject)->toBe('Your order ✓ shipped')
            ->and($event->data->destinationUrl)->toBe('https://shop.example.com/orders/1234?ref=mail')
            ->and($event->data->anchorText)->toBe('Bekijk je bestelling →')
            ->and($event->data->linkIndex)->toBe(2)
            ->and($event->data->bot?->probability)->toBe(0.02)
            // Fields the typed data does not map stay available on the payload.
            ->and($event->payload)->toBe($payload)
            ->and($event->payload['context']['project_id'])->toBe('project-1')
            ->and($event->payload['data']['tags'])->toBe([['name' => 'campaign', 'value' => 'autumn']]);

        return true;
    });
});

it('verifies the raw body, so a re-encoded body with escaped slashes is rejected', function () {
    Event::fake([MessageClicked::class]);
    $payload = backendWebhookPayload('message.clicked', backendClickedData());

    postBackendWebhook($payload, body: json_encode($payload))
        ->assertStatus(401)
        ->assertExactJson(['error' => 'Invalid signature', 'reason' => 'signature_mismatch']);

    Event::assertNotDispatched(MessageClicked::class);
});

it('rejects a backend-signed delivery outside the tolerance', function (int $offset) {
    Event::fake([MessageClicked::class]);

    postBackendWebhook(backendWebhookPayload('message.clicked', backendClickedData()), time() + $offset)
        ->assertStatus(401)
        ->assertExactJson(['error' => 'Invalid signature', 'reason' => 'timestamp_out_of_tolerance']);

    Event::assertNotDispatched(MessageClicked::class);
})->with(['too old' => -301, 'too far in the future' => 301]);

it('acknowledges a backend-signed event type this package does not know', function () {
    $received = [];
    Event::listen(WebhookEvent::class, function (WebhookEvent $event) use (&$received) {
        $received[] = $event;
    });
    $payload = backendWebhookPayload('domain.verified', ['domain_id' => 'dom-1', 'domain' => 'example.com']);

    postBackendWebhook($payload)->assertOk()->assertExactJson(['status' => 'ok']);

    expect($received)->toHaveCount(1)
        ->and($received[0])->toBeInstanceOf(UnknownWebhookEventReceived::class)
        ->and($received[0]->event)->toBe('domain.verified')
        ->and($received[0]->payload)->toBe($payload);
});

it('dispatches a typed event for a backend-signed delivery missing optional fields', function () {
    Event::fake([MessageDelivered::class]);

    // No subject, tag, tags or metadata, and a bare SMTP response.
    $payload = backendWebhookPayload('message.delivered', [
        'message_id' => 'msg-1',
        'recipient' => 'jane@example.com',
        'response' => ['status_code' => 250],
    ]);
    unset($payload['timestamp']);

    postBackendWebhook($payload)->assertOk()->assertExactJson(['status' => 'ok']);

    Event::assertDispatched(MessageDelivered::class, function (MessageDelivered $event): bool {
        expect($event->envelope->timestamp)->toBeNull()
            ->and($event->data->messageId)->toBe('msg-1')
            ->and($event->data->recipient)->toBe('jane@example.com')
            ->and($event->data->response->statusCode)->toBe(250)
            ->and($event->data->response->enhancedStatusCode)->toBeNull()
            ->and($event->data->metadata)->toBe([])
            ->and($event->data->tag)->toBeNull();

        return true;
    });
});
