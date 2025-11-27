<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailBody
{
    public function __construct(
        public ?string $text = null,
        public ?string $html = null,
    ) {}

    /**
     * @param  array{text?: string|null, html?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            text: $data['text'] ?? null,
            html: $data['html'] ?? null,
        );
    }
}
