<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\SuppressionRemovedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class SuppressionRemoved extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly SuppressionRemovedData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
