<?php

use Illuminate\Support\Facades\Event;
use Lettermint\Laravel\Webhooks\WebhookEventType;

it('verifies signatures and dispatches all current typed webhook events', function (WebhookEventType $type) {
    config()->set('lettermint.webhooks.secret', 'test-webhook-secret');
    Event::fake();

    $payloads = [
        'message.created' => [
            'message_id' => 'msg-123',
            'from' => ['email' => 'test@example.com'],
            'to' => [],
            'cc' => [],
            'bcc' => [],
            'subject' => 'Test',
            'metadata' => [],
        ],
        'message.sent' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'metadata' => [],
        ],
        'message.delivered' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 250],
            'metadata' => [],
        ],
        'message.hard_bounced' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 550],
            'metadata' => [],
        ],
        'message.soft_bounced' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'response' => ['status_code' => 450],
            'metadata' => [],
        ],
        'message.spam_complaint' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'metadata' => [],
        ],
        'message.failed' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'reason' => 'Error',
            'response' => ['status_code' => 500],
            'metadata' => [],
        ],
        'message.suppressed' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'reason' => 'Suppressed',
            'metadata' => [],
        ],
        'message.policy_rejected' => [
            'message_id' => 'msg-123',
            'subject' => 'Limited time offer!!!',
            'reason' => 'Spam score threshold exceeded',
            'score' => 7.5,
            'spam_symbols' => [
                [
                    'name' => 'BAYES_SPAM',
                    'score' => 3.5,
                    'options' => [],
                    'description' => 'Bayes spam probability is very high',
                ],
            ],
            'metadata' => [],
        ],
        'message.unsubscribed' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'unsubscribed_at' => '2024-01-01T00:00:00Z',
            'metadata' => [],
        ],
        'message.opened' => [
            'message_id' => 'msg-123',
            'subject' => 'Test',
            'metadata' => [],
            'tag' => null,
            'recipient' => 'test@example.com',
            'opened_at' => '2024-01-01T00:00:00Z',
            'first_open' => true,
            'device_type' => 'desktop',
            'client_type' => 'browser',
            'client_name' => 'Chrome',
            'user_agent' => 'Mozilla/5.0',
            'bot' => [
                'detected' => false,
                'probability' => 0,
                'classification' => 'genuine',
                'proxy_type' => null,
                'reason_codes' => [],
                'machine' => false,
                'counts_for_metrics' => true,
            ],
        ],
        'message.clicked' => [
            'message_id' => 'msg-123',
            'subject' => 'Test',
            'metadata' => [],
            'tag' => null,
            'recipient' => 'test@example.com',
            'clicked_at' => '2024-01-01T00:00:00Z',
            'destination_url' => 'https://example.com/product/123',
            'link_index' => 0,
            'anchor_text' => 'View Product',
            'first_click' => true,
            'device_type' => 'mobile',
            'client_type' => 'browser',
            'client_name' => 'Safari',
            'user_agent' => 'Mozilla/5.0',
            'bot' => [
                'detected' => false,
                'probability' => 0,
                'classification' => 'genuine',
                'proxy_type' => null,
                'reason_codes' => [],
                'machine' => false,
                'counts_for_metrics' => true,
            ],
        ],
        'message.inbound' => [
            'route' => 'route-123',
            'message_id' => 'msg-123',
            'from' => ['email' => 'test@example.com'],
            'to' => [],
            'cc' => [],
            'recipient' => 'test@example.com',
            'subject' => 'Test',
            'date' => '2024-01-01T00:00:00Z',
            'body' => [],
            'headers' => [],
            'attachments' => [],
            'is_spam' => false,
            'spam_score' => 0,
            'spam_symbols' => [],
        ],
        'suppression.added' => [
            'suppression_id' => 'suppression-123',
            'type' => 'email',
            'value' => 'test@example.com',
            'reason' => 'manual',
            'applies_to' => 'all',
        ],
        'suppression.removed' => [
            'suppression_id' => 'suppression-123',
            'type' => 'email',
            'value' => 'test@example.com',
            'reason' => 'manual',
            'applies_to' => 'all',
        ],
        'webhook.test' => [
            'message' => 'Test',
            'webhook_id' => 'webhook-123',
            'timestamp' => 1704067200,
        ],
    ];
    $common = ['message_id' => 'msg-123', 'subject' => null, 'metadata' => [], 'tag' => null];
    $scheduledAt = '2026-10-02T12:00:00Z';
    $payloads['message.scheduled'] = array_merge($common, ['scheduled_at' => $scheduledAt]);
    $payloads['message.rescheduled'] = array_merge($common, ['scheduled_at' => $scheduledAt, 'previous_scheduled_at' => '2026-10-01T12:00:00Z']);
    $payloads['message.canceled'] = array_merge($common, ['scheduled_at' => $scheduledAt, 'canceled_at' => '2026-10-01T12:00:00Z']);
    $payloads['message.released'] = array_merge($common, ['scheduled_at' => $scheduledAt, 'released_at' => $scheduledAt, 'release_delay_seconds' => 0]);
    $payloads['message.auto_replied'] = array_merge($common, ['auto_reply' => ['sender' => null, 'recipient' => null, 'subject' => null]]);

    expect($payloads)->toHaveKey($type->value);
    $payload = json_encode(['id' => 'event-123', 'event' => $type->value, 'timestamp' => '2026-10-01T12:00:00Z', 'data' => $payloads[$type->value]], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'test-webhook-secret');
    $this->call('POST', route('lettermint.webhook'), [], [], [], [
        'HTTP_X_LETTERMINT_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'HTTP_X_LETTERMINT_DELIVERY' => (string) $timestamp,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    $eventClass = 'Lettermint\\Laravel\\Events\\'.$type->name;
    $dataClass = 'Lettermint\\Laravel\\Webhooks\\Data\\'.$type->name.'Data';
    Event::assertDispatched($eventClass, function ($event) use ($type, $dataClass) {
        expect($event->envelope->id)->toBe('event-123')
            ->and($event->envelope->event)->toBe($type)
            ->and($event->data)->toBeInstanceOf($dataClass);

        return true;
    });
})->with(array_map(fn (WebhookEventType $type): array => [$type], WebhookEventType::cases()));
