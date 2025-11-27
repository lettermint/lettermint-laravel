<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageCreatedData
{
    /**
     * @param  array<string>  $to
     * @param  array<string>  $cc
     * @param  array<string>  $bcc
     * @param  array<string>  $replyTo
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public EmailAddress $from,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public string $subject,
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
            from: EmailAddress::fromArray($data['from']),
            to: $data['to'] ?? [],
            cc: $data['cc'] ?? [],
            bcc: $data['bcc'] ?? [],
            replyTo: $data['reply_to'] ?? [],
            subject: $data['subject'],
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
        );
    }
}
