<?php

use Lettermint\Laravel\Webhooks\WebhookEventType;
use Lettermint\Laravel\Webhooks\WebhookPayload;

it('can create payload from array', function () {
    $data = [
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [
            'message_id' => 'msg-456',
            'tag' => 'welcome-email',
            'metadata' => ['user_id' => '789'],
            'recipient' => 'test@example.com',
        ],
    ];

    $payload = WebhookPayload::fromArray($data);

    expect($payload->id)->toBe('webhook-123');
    expect($payload->type)->toBe(WebhookEventType::MessageDelivered);
    expect($payload->timestamp->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
    expect($payload->messageId)->toBe('msg-456');
    expect($payload->tag)->toBe('welcome-email');
    expect($payload->metadata)->toBe(['user_id' => '789']);
    expect($payload->data)->toBe([
        'message_id' => 'msg-456',
        'tag' => 'welcome-email',
        'metadata' => ['user_id' => '789'],
        'recipient' => 'test@example.com',
    ]);
    expect($payload->raw)->toBe($data);
});

it('handles missing optional fields', function () {
    $data = [
        'id' => 'webhook-123',
        'event' => 'message.sent',
        'timestamp' => '2024-01-15T10:30:00Z',
        'data' => [],
    ];

    $payload = WebhookPayload::fromArray($data);

    expect($payload->messageId)->toBeNull();
    expect($payload->tag)->toBeNull();
    expect($payload->metadata)->toBe([]);
    expect($payload->data)->toBe([]);
});

it('handles missing data key', function () {
    $data = [
        'id' => 'webhook-123',
        'event' => 'webhook.test',
        'timestamp' => '2024-01-15T10:30:00Z',
    ];

    $payload = WebhookPayload::fromArray($data);

    expect($payload->messageId)->toBeNull();
    expect($payload->tag)->toBeNull();
    expect($payload->metadata)->toBe([]);
    expect($payload->data)->toBe([]);
});

it('parses different event types', function (string $eventString, WebhookEventType $expectedType) {
    $payload = WebhookPayload::fromArray([
        'id' => 'test',
        'event' => $eventString,
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [],
    ]);

    expect($payload->type)->toBe($expectedType);
})->with([
    ['message.created', WebhookEventType::MessageCreated],
    ['message.sent', WebhookEventType::MessageSent],
    ['message.delivered', WebhookEventType::MessageDelivered],
    ['message.hard_bounced', WebhookEventType::MessageHardBounced],
    ['message.soft_bounced', WebhookEventType::MessageSoftBounced],
    ['message.spam_complaint', WebhookEventType::MessageSpamComplaint],
    ['message.failed', WebhookEventType::MessageFailed],
    ['message.suppressed', WebhookEventType::MessageSuppressed],
    ['message.unsubscribed', WebhookEventType::MessageUnsubscribed],
    ['message.inbound', WebhookEventType::MessageInbound],
    ['webhook.test', WebhookEventType::WebhookTest],
]);
