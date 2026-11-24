<?php

namespace Lettermint\Laravel\Webhooks\Data;

/**
 * Message size in bytes for a message_too_large rejection.
 */
final readonly class InboundRejectionSize
{
    /**
     * @param  int|null  $declaredBytes  The size the sender announced.
     * @param  int|null  $observedBytes  The size Lettermint received.
     */
    public function __construct(
        public ?int $declaredBytes,
        public ?int $observedBytes,
        public ?int $limitBytes,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            declaredBytes: Field::int($data, 'declared_bytes'),
            observedBytes: Field::int($data, 'observed_bytes'),
            limitBytes: Field::int($data, 'limit_bytes'),
        );
    }
}
