<?php

namespace Lettermint\Laravel\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Lettermint\Exceptions\WebhookVerificationException;
use Lettermint\Laravel\Webhooks\Exceptions\WebhookSecretNotFoundException;
use Lettermint\Webhook;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('lettermint.webhooks.secret');

        if (empty($secret)) {
            throw WebhookSecretNotFoundException::create();
        }

        $tolerance = (int) config('lettermint.webhooks.tolerance', 300);

        $webhook = new Webhook($secret, $tolerance);

        try {
            // Flatten headers array - Laravel returns array<string, list<string|null>>
            // but the SDK expects array<string, string>
            /** @var array<string, string> $headers */
            $headers = array_map(
                fn (array $value): string => $value[0] ?? '',
                $request->headers->all()
            );

            $payload = $webhook->verifyHeaders(
                $headers,
                $request->getContent()
            );

            $request->attributes->set('lettermint_webhook_payload', $payload);
        } catch (WebhookVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
