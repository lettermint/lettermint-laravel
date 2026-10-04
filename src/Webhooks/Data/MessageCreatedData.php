<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageCreatedData
{
    /**
     * @param  list<string>  $to
     * @param  list<string>  $cc
     * @param  list<string>  $bcc
     * @param  list<string>  $replyTo
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public EmailAddress $from,
        public array $to,
        public array $cc,
        public array $bcc,
        public array $replyTo,
        public ?string $subject,
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
            from: EmailAddress::fromArray(Field::array($data, 'from')),
            to: Field::strings($data, 'to'),
            cc: Field::strings($data, 'cc'),
            bcc: Field::strings($data, 'bcc'),
            replyTo: Field::strings($data, 'reply_to'),
            subject: Field::string($data, 'subject'),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
        );
    }
}
