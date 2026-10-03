<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessagePolicyRejectedData
{
    /**
     * @param  list<SpamSymbol>  $spamSymbols
     * @param  array<array-key, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public ?string $reason,
        public ?float $score,
        public array $spamSymbols,
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
            subject: Field::string($data, 'subject'),
            reason: Field::string($data, 'reason'),
            score: Field::float($data, 'score'),
            spamSymbols: Field::list($data, 'spam_symbols', SpamSymbol::fromArray(...)),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
        );
    }
}
