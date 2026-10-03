<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class SpamSymbol
{
    /**
     * @param  array<array-key, mixed>  $options
     */
    public function __construct(
        public string $name,
        public ?float $score,
        public array $options,
        public ?string $description,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: Field::string($data, 'name') ?? '',
            score: Field::float($data, 'score'),
            options: Field::array($data, 'options'),
            description: Field::string($data, 'description'),
        );
    }
}
