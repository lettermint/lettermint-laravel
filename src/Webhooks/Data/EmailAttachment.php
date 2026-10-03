<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class EmailAttachment
{
    /**
     * @param  string|null  $content  The base64 content, or null when the route delivers attachments as URLs.
     * @param  string|null  $url  A signed download URL, when the route delivers attachments as URLs.
     * @param  string|null  $expiresAt  When the download URL expires (ISO 8601).
     */
    public function __construct(
        public string $filename,
        public ?string $content,
        public ?string $contentType,
        public ?int $size,
        public ?string $contentId = null,
        public ?string $url = null,
        public ?string $expiresAt = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $contentType = Field::string($data, 'content_type');
        $filename = Field::string($data, 'filename');

        return new self(
            filename: $filename !== null && $filename !== ''
                ? $filename
                : ($contentType === 'message/rfc822' ? 'attachment.eml' : 'attachment'),
            content: Field::string($data, 'content'),
            contentType: $contentType,
            size: Field::int($data, 'size'),
            contentId: Field::string($data, 'content_id'),
            url: Field::string($data, 'url'),
            expiresAt: Field::string($data, 'expires_at'),
        );
    }

    /**
     * Decode the base64 content and return raw bytes, or null when the
     * attachment was delivered as a URL.
     */
    public function getDecodedContent(): ?string
    {
        if ($this->content === null) {
            return null;
        }

        $decoded = base64_decode($this->content, true);

        return $decoded === false ? null : $decoded;
    }
}
