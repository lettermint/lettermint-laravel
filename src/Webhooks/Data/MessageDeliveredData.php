<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageDeliveredData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public ServerResponse $response,
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
            response: ServerResponse::fromArray($data['response']),
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
        );
    }
}
