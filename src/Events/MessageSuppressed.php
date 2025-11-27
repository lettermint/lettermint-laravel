<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessageSuppressedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessageSuppressed extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessageSuppressedData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
