<?php

namespace Lettermint\Laravel\Webhooks\Data;

/**
 * The SMTP reply Lettermint sent to the sending server.
 */
final readonly class InboundRejectionSmtpReply
{
    public function __construct(
        public ?int $code,
        public ?string $enhancedCode,
        public ?string $message,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            code: Field::int($data, 'code'),
            enhancedCode: Field::string($data, 'enhanced_code'),
            message: Field::string($data, 'message'),
        );
    }
}
