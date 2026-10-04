<?php

namespace Lettermint\Laravel\Webhooks;

use Closure;
use Illuminate\Http\Request;
use Lettermint\Exceptions\WebhookVerificationException;
use Lettermint\Laravel\Webhooks\Exceptions\WebhookSecretNotFoundException;
use Lettermint\Webhook;
use Lettermint\WebhookPayload;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies a Lettermint webhook delivery with the PHP SDK and stores the
 * verified payload on the request for the WebhookController.
 *
 * Requires the X-Lettermint-Signature and X-Lettermint-Delivery headers, which
 * Lettermint sends with every delivery.
 */
class VerifyWebhookSignature
{
    /**
     * The request attribute that holds the verified WebhookPayload.
     */
    public const PAYLOAD_ATTRIBUTE = 'lettermint_webhook_payload';

    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('lettermint.webhooks.secret');

        if (! is_string($secret) || $secret === '') {
            throw WebhookSecretNotFoundException::create();
        }

        $webhook = new Webhook($secret, (int) config('lettermint.webhooks.tolerance', Webhook::DEFAULT_TOLERANCE));

        try {
            $payload = $webhook->verify($request->getContent(), $request->headers);
        } catch (WebhookVerificationException $exception) {
            return response()->json(['error' => 'Invalid signature', 'reason' => $exception->reason], 401);
        }

        $request->attributes->set(self::PAYLOAD_ATTRIBUTE, $payload);

        return $next($request);
    }

    /**
     * The verified payload of a request that passed this middleware.
     */
    public static function payload(Request $request): ?WebhookPayload
    {
        $payload = $request->attributes->get(self::PAYLOAD_ATTRIBUTE);

        return $payload instanceof WebhookPayload ? $payload : null;
    }
}
