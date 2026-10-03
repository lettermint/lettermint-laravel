<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageInboundData
{
    /**
     * @param  list<InboundEmailAddress>  $to
     * @param  list<InboundEmailAddress>  $cc
     * @param  list<EmailHeader>  $headers
     * @param  list<EmailAttachment>  $attachments
     * @param  list<SpamSymbol>  $spamSymbols
     */
    public function __construct(
        public string $route,
        public string $messageId,
        public InboundEmailAddress $from,
        public array $to,
        public array $cc,
        public ?string $recipient,
        public ?string $subaddress,
        public ?string $replyTo,
        public ?string $subject,
        public ?DateTimeImmutable $date,
        public EmailBody $body,
        public ?string $tag,
        public array $headers,
        public array $attachments,
        public bool $isSpam,
        public ?float $spamScore,
        public array $spamSymbols,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            route: Field::string($data, 'route') ?? '',
            messageId: Field::string($data, 'message_id') ?? '',
            from: InboundEmailAddress::fromArray(Field::array($data, 'from')),
            to: Field::list($data, 'to', InboundEmailAddress::fromArray(...)),
            cc: Field::list($data, 'cc', InboundEmailAddress::fromArray(...)),
            recipient: Field::string($data, 'recipient'),
            subaddress: Field::string($data, 'subaddress'),
            replyTo: Field::string($data, 'reply_to'),
            subject: Field::string($data, 'subject'),
            date: Field::date($data, 'date'),
            body: EmailBody::fromArray(Field::array($data, 'body')),
            tag: Field::string($data, 'tag'),
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
            attachments: Field::list($data, 'attachments', EmailAttachment::fromArray(...)),
            isSpam: Field::bool($data, 'is_spam', false),
            spamScore: Field::float($data, 'spam_score'),
            spamSymbols: Field::list($data, 'spam_symbols', SpamSymbol::fromArray(...)),
        );
    }
}
