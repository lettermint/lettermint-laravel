<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessageAutoRepliedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessageAutoReplied extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessageAutoRepliedData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
