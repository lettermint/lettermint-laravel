<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class MessageReleasedData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public string $scheduledAt,
        public string $releasedAt,
        public int $releaseDelaySeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            messageId: $data['message_id'],
            subject: $data['subject'] ?? null,
            metadata: $data['metadata'] ?? [],
            tag: $data['tag'] ?? null,
            scheduledAt: $data['scheduled_at'],
            releasedAt: $data['released_at'],
            releaseDelaySeconds: $data['release_delay_seconds'],
        );
    }
}
