<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class SpamSymbol
{
    /**
     * @param  array<mixed>  $options
     */
    public function __construct(
        public string $name,
        public float $score,
        public array $options,
        public ?string $description,
    ) {}

    /**
     * @param  array{name: string, score: float|int, options: array<mixed>, description: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            score: (float) $data['score'],
            options: $data['options'],
            description: $data['description'],
        );
    }
}
