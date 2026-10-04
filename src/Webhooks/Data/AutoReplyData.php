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
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $body = Field::object($data, 'body');

        return new self(
            sender: Field::string($data, 'sender'),
            recipient: Field::string($data, 'recipient'),
            subject: Field::string($data, 'subject'),
            body: $body === null ? null : EmailBody::fromArray($body),
        );
    }
}
