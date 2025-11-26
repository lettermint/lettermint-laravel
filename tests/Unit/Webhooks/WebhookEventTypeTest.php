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
use Lettermint\Laravel\Webhooks\WebhookPayload;

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

it('creates correct event class for each type', function () {
    $payload = WebhookPayload::fromArray([
        'id' => 'test-id',
        'event' => 'message.created',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [],
    ]);

    expect(WebhookEventType::MessageCreated->toEvent($payload))->toBeInstanceOf(MessageCreated::class);
    expect(WebhookEventType::MessageSent->toEvent($payload))->toBeInstanceOf(MessageSent::class);
    expect(WebhookEventType::MessageDelivered->toEvent($payload))->toBeInstanceOf(MessageDelivered::class);
    expect(WebhookEventType::MessageHardBounced->toEvent($payload))->toBeInstanceOf(MessageHardBounced::class);
    expect(WebhookEventType::MessageSoftBounced->toEvent($payload))->toBeInstanceOf(MessageSoftBounced::class);
    expect(WebhookEventType::MessageSpamComplaint->toEvent($payload))->toBeInstanceOf(MessageSpamComplaint::class);
    expect(WebhookEventType::MessageFailed->toEvent($payload))->toBeInstanceOf(MessageFailed::class);
    expect(WebhookEventType::MessageSuppressed->toEvent($payload))->toBeInstanceOf(MessageSuppressed::class);
    expect(WebhookEventType::MessageUnsubscribed->toEvent($payload))->toBeInstanceOf(MessageUnsubscribed::class);
    expect(WebhookEventType::MessageInbound->toEvent($payload))->toBeInstanceOf(MessageInbound::class);
    expect(WebhookEventType::WebhookTest->toEvent($payload))->toBeInstanceOf(WebhookTest::class);
});
