<?php

use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;
use Lettermint\Laravel\Webhooks\WebhookEventType;

it('can create envelope from array', function () {
    $data = [
        'id' => 'webhook-123',
        'event' => 'message.delivered',
        'timestamp' => '2024-01-15T10:30:00Z',
    ];

    $envelope = WebhookEnvelope::fromArray($data);

    expect($envelope->id)->toBe('webhook-123');
    expect($envelope->event)->toBe(WebhookEventType::MessageDelivered);
    expect($envelope->timestamp->format('Y-m-d H:i:s'))->toBe('2024-01-15 10:30:00');
});
