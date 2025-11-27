<?php

namespace Lettermint\Laravel\Webhooks;

use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\MessageCreated;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageFailed;
use Lettermint\Laravel\Events\MessageHardBounced;
use Lettermint\Laravel\Events\MessageInbound;
use Lettermint\Laravel\Events\MessageSent;
use Lettermint\Laravel\Events\MessageSoftBounced;
use Lettermint\Laravel\Events\MessageSpamComplaint;
use Lettermint\Laravel\Events\MessageSuppressed;
use Lettermint\Laravel\Events\MessageUnsubscribed;
use Lettermint\Laravel\Events\WebhookTest;
use Lettermint\Laravel\Webhooks\Data\MessageCreatedData;
use Lettermint\Laravel\Webhooks\Data\MessageDeliveredData;
use Lettermint\Laravel\Webhooks\Data\MessageFailedData;
use Lettermint\Laravel\Webhooks\Data\MessageHardBouncedData;
use Lettermint\Laravel\Webhooks\Data\MessageInboundData;
use Lettermint\Laravel\Webhooks\Data\MessageSentData;
use Lettermint\Laravel\Webhooks\Data\MessageSoftBouncedData;
use Lettermint\Laravel\Webhooks\Data\MessageSpamComplaintData;
use Lettermint\Laravel\Webhooks\Data\MessageSuppressedData;
use Lettermint\Laravel\Webhooks\Data\MessageUnsubscribedData;
use Lettermint\Laravel\Webhooks\Data\WebhookEnvelope;
use Lettermint\Laravel\Webhooks\Data\WebhookTestData;

enum WebhookEventType: string
{
    case MessageCreated = 'message.created';
    case MessageSent = 'message.sent';
    case MessageDelivered = 'message.delivered';
    case MessageHardBounced = 'message.hard_bounced';
    case MessageSoftBounced = 'message.soft_bounced';
    case MessageSpamComplaint = 'message.spam_complaint';
    case MessageFailed = 'message.failed';
    case MessageSuppressed = 'message.suppressed';
    case MessageUnsubscribed = 'message.unsubscribed';
    case MessageInbound = 'message.inbound';
    case WebhookTest = 'webhook.test';

    public function isBounce(): bool
    {
        return in_array($this, [self::MessageHardBounced, self::MessageSoftBounced], true);
    }

    public function isDeliveryIssue(): bool
    {
        return in_array($this, [
            self::MessageHardBounced,
            self::MessageSoftBounced,
            self::MessageFailed,
            self::MessageSuppressed,
        ], true);
    }

    /**
     * Create the appropriate event instance from raw webhook payload.
     *
     * @param  array<string, mixed>  $rawPayload
     */
    public function toEvent(array $rawPayload): LettermintWebhookEvent
    {
        $envelope = WebhookEnvelope::fromArray($rawPayload);
        $data = $rawPayload['data'] ?? [];

        return match ($this) {
            self::MessageCreated => new MessageCreated($envelope, MessageCreatedData::fromArray($data)),
            self::MessageSent => new MessageSent($envelope, MessageSentData::fromArray($data)),
            self::MessageDelivered => new MessageDelivered($envelope, MessageDeliveredData::fromArray($data)),
            self::MessageHardBounced => new MessageHardBounced($envelope, MessageHardBouncedData::fromArray($data)),
            self::MessageSoftBounced => new MessageSoftBounced($envelope, MessageSoftBouncedData::fromArray($data)),
            self::MessageSpamComplaint => new MessageSpamComplaint($envelope, MessageSpamComplaintData::fromArray($data)),
            self::MessageFailed => new MessageFailed($envelope, MessageFailedData::fromArray($data)),
            self::MessageSuppressed => new MessageSuppressed($envelope, MessageSuppressedData::fromArray($data)),
            self::MessageUnsubscribed => new MessageUnsubscribed($envelope, MessageUnsubscribedData::fromArray($data)),
            self::MessageInbound => new MessageInbound($envelope, MessageInboundData::fromArray($data)),
            self::WebhookTest => new WebhookTest($envelope, WebhookTestData::fromArray($data)),
        };
    }
}
