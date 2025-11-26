<?php

namespace Lettermint\Laravel\Webhooks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        $payload = WebhookPayload::fromArray(
            $request->attributes->get('lettermint_webhook_payload')
        );

        event($payload->type->toEvent($payload));

        return response()->json(['status' => 'ok']);
    }
}
