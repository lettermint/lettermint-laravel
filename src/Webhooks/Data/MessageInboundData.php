<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageInboundData
{
    /**
     * @param  array<InboundEmailAddress>  $to
     * @param  array<InboundEmailAddress>  $cc
     * @param  array<EmailHeader>  $headers
     * @param  array<EmailAttachment>  $attachments
     * @param  array<SpamSymbol>  $spamSymbols
     */
    public function __construct(
        public string $route,
        public string $messageId,
        public InboundEmailAddress $from,
        public array $to,
        public array $cc,
        public string $recipient,
        public ?string $subaddress,
        public ?string $replyTo,
        public string $subject,
        public DateTimeImmutable $date,
        public EmailBody $body,
        public ?string $tag,
        public array $headers,
        public array $attachments,
        public bool $isSpam,
        public float $spamScore,
        public array $spamSymbols,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            route: $data['route'],
            messageId: $data['message_id'],
            from: InboundEmailAddress::fromArray($data['from']),
            to: array_map(
                fn (array $addr) => InboundEmailAddress::fromArray($addr),
                $data['to'] ?? []
            ),
            cc: array_map(
                fn (array $addr) => InboundEmailAddress::fromArray($addr),
                $data['cc'] ?? []
            ),
            recipient: $data['recipient'],
            subaddress: $data['subaddress'] ?? null,
            replyTo: $data['reply_to'] ?? null,
            subject: $data['subject'],
            date: new DateTimeImmutable($data['date']),
            body: EmailBody::fromArray($data['body'] ?? []),
            tag: $data['tag'] ?? null,
            headers: array_map(
                fn (array $header) => EmailHeader::fromArray($header),
                $data['headers'] ?? []
            ),
            attachments: array_map(
                fn (array $attachment) => EmailAttachment::fromArray($attachment),
                $data['attachments'] ?? []
            ),
            isSpam: $data['is_spam'] ?? false,
            spamScore: (float) ($data['spam_score'] ?? 0),
            spamSymbols: array_map(
                fn (array $symbol) => SpamSymbol::fromArray($symbol),
                $data['spam_symbols'] ?? []
            ),
        );
    }
}
