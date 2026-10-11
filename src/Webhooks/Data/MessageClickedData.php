<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageClickedData
{
    /**
     * @param  array<array-key, mixed>  $metadata
     * @param  list<EmailHeader>  $headers
     */
    public function __construct(
        public string $messageId,
        public ?string $subject,
        public array $metadata,
        public ?string $tag,
        public string $recipient,
        public ?DateTimeImmutable $clickedAt,
        public ?string $destinationUrl,
        public ?int $linkIndex,
        public ?string $anchorText,
        public bool $firstClick,
        public ?string $deviceType,
        public ?string $clientType,
        public ?string $clientName,
        public ?string $userAgent,
        public ?BotDetectionData $bot,
        public array $headers = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $bot = Field::object($data, 'bot');

        return new self(
            messageId: Field::string($data, 'message_id') ?? '',
            subject: Field::string($data, 'subject'),
            metadata: Field::array($data, 'metadata'),
            tag: Field::string($data, 'tag'),
            recipient: Field::string($data, 'recipient') ?? '',
            clickedAt: Field::date($data, 'clicked_at'),
            destinationUrl: Field::string($data, 'destination_url'),
            linkIndex: Field::int($data, 'link_index'),
            anchorText: Field::string($data, 'anchor_text'),
            firstClick: Field::bool($data, 'first_click', false),
            deviceType: Field::string($data, 'device_type'),
            clientType: Field::string($data, 'client_type'),
            clientName: Field::string($data, 'client_name'),
            userAgent: Field::string($data, 'user_agent'),
            bot: $bot === null ? null : BotDetectionData::fromArray($bot),
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
        );
    }
}
