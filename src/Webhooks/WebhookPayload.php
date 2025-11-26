<?php

namespace Lettermint\Laravel\Webhooks;

use DateTimeImmutable;

final readonly class WebhookPayload
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $id,
        public WebhookEventType $type,
        public DateTimeImmutable $timestamp,
        public ?string $messageId,
        public ?string $tag,
        public array $metadata,
        public array $data,
        public array $raw,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            id: $payload['id'],
            type: WebhookEventType::from($payload['event']),
            timestamp: new DateTimeImmutable($payload['timestamp']),
            messageId: $payload['data']['message_id'] ?? null,
            tag: $payload['data']['tag'] ?? null,
            metadata: $payload['data']['metadata'] ?? [],
            data: $payload['data'] ?? [],
            raw: $payload,
        );
    }
}
