<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailAddress
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {}

    /**
     * @param  array{email: string, name?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            email: $data['email'],
            name: $data['name'] ?? null,
        );
    }
}
