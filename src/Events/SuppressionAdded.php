<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\SuppressionAddedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class SuppressionAdded extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly SuppressionAddedData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
