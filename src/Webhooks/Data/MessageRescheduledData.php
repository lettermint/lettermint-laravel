<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageRescheduledData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public string $previousScheduledAt,
        public string $scheduledAt,
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
            previousScheduledAt: $data['previous_scheduled_at'],
            scheduledAt: $data['scheduled_at'],
        );
    }
}
