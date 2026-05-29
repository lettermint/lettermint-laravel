<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageOpenedData
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public string $recipient,
        public DateTimeImmutable $openedAt,
        public bool $firstOpen,
        public ?string $deviceType,
        public ?string $clientType,
        public ?string $clientName,
        public ?string $userAgent,
        public ?BotDetectionData $bot,
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
            recipient: $data['recipient'],
            openedAt: new DateTimeImmutable($data['opened_at']),
            firstOpen: $data['first_open'] ?? false,
            deviceType: $data['device_type'] ?? null,
            clientType: $data['client_type'] ?? null,
            clientName: $data['client_name'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            bot: isset($data['bot']) && is_array($data['bot']) ? BotDetectionData::fromArray($data['bot']) : null,
        );
    }
}
