<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class WebhookTestData
{
    public function __construct(
        public string $message,
        public string $webhookId,
        public ?int $timestamp,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: Field::string($data, 'message') ?? '',
            webhookId: Field::string($data, 'webhook_id') ?? '',
            timestamp: Field::int($data, 'timestamp'),
        );
    }
}
