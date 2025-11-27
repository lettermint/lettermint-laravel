<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailAttachment
{
    public function __construct(
        public string $filename,
        public string $content,
        public string $contentType,
        public int $size,
        public ?string $contentId = null,
    ) {}

    /**
     * @param  array{filename: string, content: string, content_type: string, size: int, content_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filename: $data['filename'],
            content: $data['content'],
            contentType: $data['content_type'],
            size: $data['size'],
            contentId: $data['content_id'] ?? null,
        );
    }

    /**
     * Decode the base64 content and return raw bytes.
     */
    public function getDecodedContent(): string
    {
        return base64_decode($this->content);
    }
}
