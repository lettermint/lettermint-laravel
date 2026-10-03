<?php

namespace Lettermint\Laravel\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->attributes->get('lettermint_webhook_payload');

        $eventName = is_string($payload['event'] ?? null) ? $payload['event'] : '';
        $eventType = WebhookEventType::tryFrom($eventName);

        // Event types added to the API after this package version must not fail
        // the delivery, or Lettermint retries it and may disable the endpoint.
        event($eventType === null
            ? new UnknownWebhookEventReceived($eventName, $payload)
            : $eventType->toEvent($payload));

        return response()->json(['status' => 'ok']);
    }
}
