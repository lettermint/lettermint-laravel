<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageHardBouncedData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public ServerResponse $response,
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
            response: ServerResponse::fromArray(Field::array($data, 'response')),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
        );
    }
}
