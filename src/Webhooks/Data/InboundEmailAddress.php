<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class InboundEmailAddress
{
    public function __construct(
        public string $email,
        public ?string $name = null,
        public ?string $subaddress = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: Field::string($data, 'email') ?? '',
            name: Field::string($data, 'name'),
            subaddress: Field::string($data, 'subaddress'),
        );
    }
}
