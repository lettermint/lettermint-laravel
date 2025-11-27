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
     * @param  array{id: string, event: string, timestamp: string}  $data
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
