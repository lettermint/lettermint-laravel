<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageSuppressedData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $recipient,
        public string $reason,
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
            reason: $data['reason'],
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
        );
    }
}
