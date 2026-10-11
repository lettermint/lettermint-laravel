<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageSuppressedData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     * @param  list<EmailHeader>  $headers
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public ?string $reason,
        public array $metadata,
        public ?string $tag,
        public array $headers = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: Field::string($data, 'message_id') ?? '',
            recipient: Field::string($data, 'recipient') ?? '',
            reason: Field::string($data, 'reason'),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
        );
    }
}
