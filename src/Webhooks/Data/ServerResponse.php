<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class ServerResponse
{
    public function __construct(
        public int $statusCode,
        public ?string $enhancedStatusCode = null,
        public ?string $content = null,
    ) {}

    /**
     * @param  array{status_code: int, enhanced_status_code?: string|null, content?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            statusCode: $data['status_code'],
            enhancedStatusCode: $data['enhanced_status_code'] ?? null,
            content: $data['content'] ?? null,
        );
    }
}
