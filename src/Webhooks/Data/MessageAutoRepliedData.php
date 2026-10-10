<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageAutoRepliedData
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
        public AutoReplyData $autoReply,
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
            autoReply: AutoReplyData::fromArray(Field::array($data, 'auto_reply')),
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
        );
    }
}
