<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailHeader
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Field::string($data, 'name') ?? '',
            value: Field::string($data, 'value') ?? '',
        );
    }
}
