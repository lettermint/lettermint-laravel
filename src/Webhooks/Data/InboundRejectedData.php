<?php

namespace Lettermint\Laravel\Webhooks\Data;

/**
 * Lettermint permanently rejected mail for an inbound route during the SMTP conversation.
 * The message never reaches the application as message.inbound.
 */
final readonly class InboundRejectedData
{
    /**
     * @param  string|null  $stage  `rcpt` when the message was refused before it was received, `data` after.
     * @param  string|null  $reason  `message_too_large`, `attachment_blocked` or `mail_loop_detected`.
     * @param  list<string>  $recipients  Envelope recipients of the route.
     * @param  InboundRejectionSize|null  $size  Only set for `message_too_large`.
     */
    public function __construct(
        public string $rejectionId,
        public string $route,
        public ?string $stage,
        public ?string $reason,
        public bool $permanent,
        public array $recipients,
        public InboundRejectionSmtpReply $smtp,
        public InboundRejectionEnvelope $envelope,
        public ?InboundRejectionSize $size,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $size = Field::object($data, 'size');

        return new self(
            rejectionId: Field::string($data, 'rejection_id') ?? '',
            route: Field::string($data, 'route') ?? '',
            stage: Field::string($data, 'stage'),
            reason: Field::string($data, 'reason'),
            permanent: Field::bool($data, 'permanent', true),
            recipients: Field::strings($data, 'recipients'),
            smtp: InboundRejectionSmtpReply::fromArray(Field::array($data, 'smtp')),
            envelope: InboundRejectionEnvelope::fromArray(Field::array($data, 'envelope')),
            size: $size === null ? null : InboundRejectionSize::fromArray($size),
        );
    }
}
