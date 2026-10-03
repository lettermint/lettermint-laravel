<?php

namespace Lettermint\Laravel\Contracts;

use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;

/**
 * Marker interface implemented by every event this package dispatches for an
 * incoming webhook: all typed events (via the abstract
 * {@see LettermintWebhookEvent} base class) and
 * {@see UnknownWebhookEventReceived}.
 *
 * Laravel's dispatcher matches listeners on an event's own class and the
 * interfaces it implements, but not on its parent classes. Register a listener
 * on this interface to receive every webhook event, including event types this
 * package version does not know yet.
 */
interface WebhookEvent {}
