<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

abstract class LettermintWebhookEvent
{
    abstract public function getEnvelope(): WebhookEnvelope;
}
