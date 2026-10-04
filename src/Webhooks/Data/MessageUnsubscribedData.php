<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageUnsubscribedData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public ?DateTimeImmutable $unsubscribedAt,
        public array $metadata,
        public ?string $tag,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: Field::string($data, 'message_id') ?? '',
            recipient: Field::string($data, 'recipient') ?? '',
            unsubscribedAt: Field::date($data, 'unsubscribed_at'),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
        );
    }
}
