<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageScheduledData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public ?string $scheduledAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: Field::string($data, 'message_id') ?? '',
            subject: Field::string($data, 'subject'),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
            scheduledAt: Field::string($data, 'scheduled_at'),
        );
    }
}
