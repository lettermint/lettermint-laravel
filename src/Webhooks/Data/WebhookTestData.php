<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class WebhookTestData
{
    public function __construct(
        public string $message,
        public string $webhookId,
        public int $timestamp,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            message: $data['message'],
            webhookId: $data['webhook_id'],
            timestamp: $data['timestamp'],
        );
    }
}
