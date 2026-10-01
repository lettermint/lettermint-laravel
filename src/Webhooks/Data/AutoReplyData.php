<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class AutoReplyData
{
    public function __construct(
        public ?string $sender,
        public ?string $recipient,
        public ?string $subject,
        public ?EmailBody $body,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sender: $data['sender'] ?? null,
            recipient: $data['recipient'] ?? null,
            subject: $data['subject'] ?? null,
            body: isset($data['body']) ? EmailBody::fromArray($data['body']) : null,
        );
    }
}
