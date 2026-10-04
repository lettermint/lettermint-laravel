<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessageInboundData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessageInbound extends LettermintWebhookEvent
{
    /**
     * @param  array<string, mixed>  $payload  The complete verified webhook payload, including fields the typed data does not map.
     */
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessageInboundData $data,
        public readonly array $payload = [],
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
