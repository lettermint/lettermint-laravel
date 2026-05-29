<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageClickedData
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
        public DateTimeImmutable $clickedAt,
        public string $destinationUrl,
        public int $linkIndex,
        public ?string $anchorText,
        public bool $firstClick,
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
            clickedAt: new DateTimeImmutable($data['clicked_at']),
            destinationUrl: $data['destination_url'],
            linkIndex: (int) $data['link_index'],
            anchorText: $data['anchor_text'] ?? null,
            firstClick: $data['first_click'] ?? false,
            deviceType: $data['device_type'] ?? null,
            clientType: $data['client_type'] ?? null,
            clientName: $data['client_name'] ?? null,
            userAgent: $data['user_agent'] ?? null,
            bot: isset($data['bot']) && is_array($data['bot']) ? BotDetectionData::fromArray($data['bot']) : null,
        );
    }
}
