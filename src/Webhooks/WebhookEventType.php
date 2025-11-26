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

    public function toEvent(WebhookPayload $payload): LettermintWebhookEvent
    {
        return match ($this) {
            self::MessageCreated => new MessageCreated($payload),
            self::MessageSent => new MessageSent($payload),
            self::MessageDelivered => new MessageDelivered($payload),
            self::MessageHardBounced => new MessageHardBounced($payload),
            self::MessageSoftBounced => new MessageSoftBounced($payload),
            self::MessageSpamComplaint => new MessageSpamComplaint($payload),
            self::MessageFailed => new MessageFailed($payload),
            self::MessageSuppressed => new MessageSuppressed($payload),
            self::MessageUnsubscribed => new MessageUnsubscribed($payload),
            self::MessageInbound => new MessageInbound($payload),
            self::WebhookTest => new WebhookTest($payload),
        };
    }
}
