<?php

namespace Lettermint\Laravel\Webhooks\Data;

use DateTimeImmutable;

final readonly class MessageOpenedData
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
        public ?DateTimeImmutable $openedAt,
        public bool $firstOpen,
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
            openedAt: Field::date($data, 'opened_at'),
            firstOpen: Field::bool($data, 'first_open', false),
            deviceType: Field::string($data, 'device_type'),
            clientType: Field::string($data, 'client_type'),
            clientName: Field::string($data, 'client_name'),
            userAgent: Field::string($data, 'user_agent'),
            bot: $bot === null ? null : BotDetectionData::fromArray($bot),
            headers: Field::list($data, 'headers', EmailHeader::fromArray(...)),
        );
    }
}
