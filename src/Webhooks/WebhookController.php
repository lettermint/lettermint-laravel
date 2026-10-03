<?php

namespace Lettermint\Laravel\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;
use LogicException;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = VerifyWebhookSignature::payload($request);

        if ($payload === null) {
            throw new LogicException(sprintf('%s must run behind the %s middleware.', self::class, VerifyWebhookSignature::class));
        }

        $eventType = WebhookEventType::tryFrom($payload->event);

        // Event types added to the API after this package version must not fail
        // the delivery, or Lettermint retries it and may disable the endpoint.
        event($eventType === null
            ? new UnknownWebhookEventReceived($payload->event, $payload->toArray())
            : $eventType->toEvent($payload->toArray()));

        return response()->json(['status' => 'ok']);
    }
}
