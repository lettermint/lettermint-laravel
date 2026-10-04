<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessageAutoRepliedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessageAutoReplied extends LettermintWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload  The complete verified webhook payload, including fields the typed data does not map.
     */
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessageAutoRepliedData $data,
        public readonly array $payload = [],
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
