<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailBody
{
    public function __construct(
        public ?string $text = null,
        public ?string $html = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: Field::string($data, 'text'),
            html: Field::string($data, 'html'),
        );
    }
}
