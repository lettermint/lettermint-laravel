<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;
use Lettermint\Laravel\Webhooks\WebhookEventType;

final readonly class WebhookEnvelope
{
    /**
     * @param  string  $id  The delivery ID ('' if the payload has none).
     * @param  DateTimeImmutable|null  $timestamp  When the event occurred, or null if missing or unreadable.
     */
    public function __construct(
        public string $id,
        public WebhookEventType $event,
        public ?DateTimeImmutable $timestamp,
    ) {}

    /**
     * Only for event types WebhookEventType knows; the webhook controller
     * dispatches UnknownWebhookEventReceived for any other type.
     *
     * @param  array<array-key, mixed>  $data
     *
     * @throws \ValueError When the event type is unknown.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: Field::string($data, 'id') ?? '',
            event: WebhookEventType::from(Field::string($data, 'event') ?? ''),
            timestamp: Field::date($data, 'timestamp'),
        );
    }
}
