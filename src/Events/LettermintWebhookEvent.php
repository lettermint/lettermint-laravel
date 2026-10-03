<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Contracts\WebhookEvent;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

abstract class LettermintWebhookEvent implements WebhookEvent
{
    abstract public function getEnvelope(): WebhookEnvelope;
}
