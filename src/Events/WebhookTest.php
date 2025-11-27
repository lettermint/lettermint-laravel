<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;
use Lettermint\Laravel\Webhooks\Data\WebhookTestData;

final class WebhookTest extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly WebhookTestData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
