<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\WebhookPayload;

abstract class LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookPayload $payload
    ) {}
}
