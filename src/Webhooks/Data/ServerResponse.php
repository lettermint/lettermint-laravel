<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class ServerResponse
{
    public function __construct(
        public ?int $statusCode,
        public ?string $enhancedStatusCode = null,
        public ?string $content = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statusCode: Field::int($data, 'status_code'),
            enhancedStatusCode: Field::string($data, 'enhanced_status_code'),
            content: Field::string($data, 'content'),
        );
    }
}
