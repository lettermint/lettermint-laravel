<?php

namespace Lettermint\Laravel\Events;

/**
 * Dispatched for a verified webhook whose event type this package version does
 * not know yet, for example an event type Lettermint added after this release.
 *
 * The delivery is still acknowledged with a 2xx response so Lettermint does not
 * retry it or disable the endpoint. Known event types keep dispatching their
 * typed events and never dispatch this one.
 */
final class UnknownWebhookEventReceived
{
    /**
     * @param  string  $event  The raw event name, e.g. "message.some_new_event" ('' when missing).
     * @param  array<string, mixed>  $payload  The complete verified webhook payload.
     */
    public function __construct(
        public readonly string $event,
        public readonly array $payload,
    ) {}
}
