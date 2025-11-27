<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessageInboundData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessageInbound extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessageInboundData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
