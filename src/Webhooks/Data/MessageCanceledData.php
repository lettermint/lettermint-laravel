<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageCanceledData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public string $scheduledAt,
        public string $canceledAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            subject: $data['subject'] ?? null,
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
            scheduledAt: $data['scheduled_at'],
            canceledAt: $data['canceled_at'],
        );
    }
}
