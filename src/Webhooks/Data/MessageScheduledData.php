<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageScheduledData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     * @param  list<EmailHeader>  $headers
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public ?string $scheduledAt,
        public array $headers = [],
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
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
        );
    }
}
