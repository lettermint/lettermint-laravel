<?php

namespace Lettermint\Laravel\Events;

use Lettermint\Laravel\Webhooks\Data\MessagePolicyRejectedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;

final class MessagePolicyRejected extends LettermintWebhookEvent
{
    public function __construct(
        public readonly WebhookEnvelope $envelope,
        public readonly MessagePolicyRejectedData $data,
    ) {}

    public function getEnvelope(): WebhookEnvelope
    {
        return $this->envelope;
    }
}
