<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessagePolicyRejectedData
{
    /**
     * @param  array<int, SpamSymbol>  $spamSymbols
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public string $subject,
        public string $reason,
        public float $score,
        public array $spamSymbols,
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
            subject: $data['subject'],
            reason: $data['reason'],
            score: (float) $data['score'],
            spamSymbols: array_map(
                fn (array $symbol): SpamSymbol => SpamSymbol::fromArray($symbol),
                $data['spam_symbols'] ?? [],
            ),
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
        );
    }
}
