<?php

use Lettermint\Laravel\Events\MessageCreated;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageFailed;
use Lettermint\Laravel\Events\MessageHardBounced;
use Lettermint\Laravel\Events\MessageInbound;
use Lettermint\Laravel\Events\MessageSent;
use Lettermint\Laravel\Events\MessageSoftBounced;
use Lettermint\Laravel\Events\MessageSpamComplaint;
use Lettermint\Laravel\Events\MessageSuppressed;
use Lettermint\Laravel\Events\MessageUnsubscribed;
use Lettermint\Laravel\Events\WebhookTest;
use Lettermint\Laravel\Webhooks\WebhookEventType;

it('can create event type from string value', function () {
    expect(WebhookEventType::from('message.created'))->toBe(WebhookEventType::MessageCreated);
    expect(WebhookEventType::from('message.sent'))->toBe(WebhookEventType::MessageSent);
    expect(WebhookEventType::from('message.delivered'))->toBe(WebhookEventType::MessageDelivered);
    expect(WebhookEventType::from('message.hard_bounced'))->toBe(WebhookEventType::MessageHardBounced);
    expect(WebhookEventType::from('message.soft_bounced'))->toBe(WebhookEventType::MessageSoftBounced);
    expect(WebhookEventType::from('message.spam_complaint'))->toBe(WebhookEventType::MessageSpamComplaint);
    expect(WebhookEventType::from('message.failed'))->toBe(WebhookEventType::MessageFailed);
    expect(WebhookEventType::from('message.suppressed'))->toBe(WebhookEventType::MessageSuppressed);
    expect(WebhookEventType::from('message.unsubscribed'))->toBe(WebhookEventType::MessageUnsubscribed);
    expect(WebhookEventType::from('message.inbound'))->toBe(WebhookEventType::MessageInbound);
    expect(WebhookEventType::from('webhook.test'))->toBe(WebhookEventType::WebhookTest);
});

it('identifies bounce event types', function () {
    expect(WebhookEventType::MessageHardBounced->isBounce())->toBeTrue();
    expect(WebhookEventType::MessageSoftBounced->isBounce())->toBeTrue();
    expect(WebhookEventType::MessageDelivered->isBounce())->toBeFalse();
    expect(WebhookEventType::MessageSent->isBounce())->toBeFalse();
});

it('identifies delivery issue event types', function () {
    expect(WebhookEventType::MessageHardBounced->isDeliveryIssue())->toBeTrue();
    expect(WebhookEventType::MessageSoftBounced->isDeliveryIssue())->toBeTrue();
    expect(WebhookEventType::MessageFailed->isDeliveryIssue())->toBeTrue();
    expect(WebhookEventType::MessageSuppressed->isDeliveryIssue())->toBeTrue();
    expect(WebhookEventType::MessageDelivered->isDeliveryIssue())->toBeFalse();
    expect(WebhookEventType::MessageSent->isDeliveryIssue())->toBeFalse();
});

it('creates correct event class for MessageDelivered', function () {
    $payload = [
        'id' => 'test-id',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'response' => [
                'status_code' => 250,
                'enhanced_status_code' => '2.0.0',
                'content' => 'OK',
            ],
            'metadata' => [],
            'tag' => null,
        ],
    ];

    $event = WebhookEventType::MessageDelivered->toEvent($payload);

    expect($event)->toBeInstanceOf(MessageDelivered::class);
    expect($event->envelope->id)->toBe('test-id');
    expect($event->data->messageId)->toBe('msg-123');
    expect($event->data->recipient)->toBe('test@example.com');
    expect($event->data->response->statusCode)->toBe(250);
});

it('creates correct event class for MessageCreated', function () {
    $payload = [
        'id' => 'test-id',
        'event' => 'message.created',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [
            'message_id' => 'msg-123',
            'from' => ['email' => 'sender@example.com', 'name' => 'Sender'],
            'to' => ['recipient@example.com'],
            'cc' => [],
            'bcc' => [],
            'reply_to' => null,
            'subject' => 'Test Subject',
            'metadata' => [],
            'tag' => null,
        ],
    ];

    $event = WebhookEventType::MessageCreated->toEvent($payload);

    expect($event)->toBeInstanceOf(MessageCreated::class);
    expect($event->data->from->email)->toBe('sender@example.com');
    expect($event->data->subject)->toBe('Test Subject');
});

it('creates correct event class for WebhookTest', function () {
    $payload = [
        'id' => 'test-id',
        'event' => 'webhook.test',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [
            'message' => 'Test webhook',
            'webhook_id' => 'webhook-123',
            'timestamp' => 1704067200,
        ],
    ];

    $event = WebhookEventType::WebhookTest->toEvent($payload);

    expect($event)->toBeInstanceOf(WebhookTest::class);
    expect($event->data->message)->toBe('Test webhook');
    expect($event->data->webhookId)->toBe('webhook-123');
});

it('creates correct event class for each type', function (string $eventType, string $eventClass) {
    // Common minimal payload structure for each event type
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
        'message.unsubscribed' => [
            'message_id' => 'msg-123',
            'recipient' => 'test@example.com',
            'unsubscribed_at' => '2024-01-01T00:00:00Z',
            'metadata' => [],
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
        'webhook.test' => [
            'message' => 'Test',
            'webhook_id' => 'webhook-123',
            'timestamp' => 1704067200,
        ],
    ];

    $payload = [
        'id' => 'test-id',
        'event' => $eventType,
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => $payloads[$eventType],
    ];

    $event = WebhookEventType::from($eventType)->toEvent($payload);

    expect($event)->toBeInstanceOf($eventClass);
})->with([
    ['message.created', MessageCreated::class],
    ['message.sent', MessageSent::class],
    ['message.delivered', MessageDelivered::class],
    ['message.hard_bounced', MessageHardBounced::class],
    ['message.soft_bounced', MessageSoftBounced::class],
    ['message.spam_complaint', MessageSpamComplaint::class],
    ['message.failed', MessageFailed::class],
    ['message.suppressed', MessageSuppressed::class],
    ['message.unsubscribed', MessageUnsubscribed::class],
    ['message.inbound', MessageInbound::class],
    ['webhook.test', WebhookTest::class],
]);
