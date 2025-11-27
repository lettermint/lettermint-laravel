<?php

namespace Lettermint\Laravel\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->attributes->get('lettermint_webhook_payload');

        $eventType = WebhookEventType::from($payload['event']);

        event($eventType->toEvent($payload));

        return response()->json(['status' => 'ok']);
    }
}
