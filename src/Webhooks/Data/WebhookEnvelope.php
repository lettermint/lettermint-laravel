<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;
use Lettermint\Laravel\Webhooks\WebhookEventType;

final readonly class WebhookEnvelope
{
    public function __construct(
        public string $id,
        public WebhookEventType $event,
        public DateTimeImmutable $timestamp,
    ) {}

    /**
     * Only for event types WebhookEventType knows; the webhook controller
     * dispatches UnknownWebhookEventReceived for any other type.
     *
     * @param  array{id: string, event: string, timestamp: string}  $data
     *
     * @throws \ValueError When the event type is unknown.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            event: WebhookEventType::from($data['event']),
            timestamp: new DateTimeImmutable($data['timestamp']),
        );
    }
}
