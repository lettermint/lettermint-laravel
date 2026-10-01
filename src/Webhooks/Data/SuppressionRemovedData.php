<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class SuppressionRemovedData
{
    public function __construct(
        public string $suppressionId,
        public string $type,
        public string $value,
        public string $reason,
        public string $appliesTo,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            suppressionId: $data['suppression_id'],
            type: $data['type'],
            value: $data['value'],
            reason: $data['reason'],
            appliesTo: $data['applies_to'],
        );
    }
}
