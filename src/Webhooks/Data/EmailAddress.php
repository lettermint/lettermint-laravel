<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailAddress
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: Field::string($data, 'email') ?? '',
            name: Field::string($data, 'name'),
        );
    }
}
