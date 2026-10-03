<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class SuppressionAddedData
{
    public function __construct(
        public string $suppressionId,
        public string $type,
        public string $value,
        public string $reason,
        public string $appliesTo,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            suppressionId: Field::string($data, 'suppression_id') ?? '',
            type: Field::string($data, 'type') ?? '',
            value: Field::string($data, 'value') ?? '',
            reason: Field::string($data, 'reason') ?? '',
            appliesTo: Field::string($data, 'applies_to') ?? '',
        );
    }
}
