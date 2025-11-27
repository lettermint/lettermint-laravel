<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageUnsubscribedData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public DateTimeImmutable $unsubscribedAt,
        public array $metadata,
        public ?string $tag,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            recipient: $data['recipient'],
            unsubscribedAt: new DateTimeImmutable($data['unsubscribed_at']),
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
        );
    }
}
