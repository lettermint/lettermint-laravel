<?php

use Illuminate\Http\Request;
use Lettermint\Exceptions\LettermintConfigException;
use Lettermint\Laravel\Webhooks\Exceptions\WebhookSecretNotFoundException;
use Lettermint\Laravel\Webhooks\VerifyWebhookSignature;
use Lettermint\WebhookPayload;

function createSignedRequest(string $payload, string $secret, ?int $timestamp = null): Request
{
    $timestamp = $timestamp ?? time();
    $signedContent = $timestamp.'.'.$payload;
    $signature = hash_hmac('sha256', $signedContent, $secret);

    $request = Request::create(
        '/lettermint/webhook',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'HTTP_X_LETTERMINT_DELIVERY' => (string) $timestamp,
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    return $request;
}

it('passes valid webhook through middleware', function () {
    config()->set('lettermint.webhooks.secret', 'test-secret');
    config()->set('lettermint.webhooks.tolerance', 300);

    $payload = json_encode([
        'id' => 'test-123',
        'event' => 'message.sent',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [],
    ]);

    $request = createSignedRequest($payload, 'test-secret');
    $middleware = new VerifyWebhookSignature;

    $response = $middleware->handle($request, fn ($req) => response()->json(['passed' => true]));

    expect($response->getStatusCode())->toBe(200);

    $verified = VerifyWebhookSignature::payload($request);

    expect($verified)->toBeInstanceOf(WebhookPayload::class)
        ->and($request->attributes->get(VerifyWebhookSignature::PAYLOAD_ATTRIBUTE))->toBe($verified)
        ->and($verified->event)->toBe('message.sent')
        ->and($verified->id)->toBe('test-123')
        ->and($verified['event'])->toBe('message.sent')
        ->and($verified->toArray())->toBe([
            'id' => 'test-123',
            'event' => 'message.sent',
            'timestamp' => '2024-01-01T00:00:00Z',
            'data' => [],
        ]);
});

it('rejects deliveries with the reason the SDK reports', function (Closure $tamper, string $reason) {
    config()->set('lettermint.webhooks.secret', 'test-secret');

    $payload = json_encode(['id' => 'test-123', 'event' => 'message.sent', 'data' => []]);
    $request = createSignedRequest($payload, 'test-secret');
    $request = $tamper($request, $payload);

    $response = (new VerifyWebhookSignature)->handle($request, fn () => response()->json(['passed' => true]));

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true))->toBe(['error' => 'Invalid signature', 'reason' => $reason])
        ->and(VerifyWebhookSignature::payload($request))->toBeNull();
})->with([
    'missing delivery header' => [function (Request $request) {
        $request->headers->remove('X-Lettermint-Delivery');

        return $request;
    }, 'delivery_header_missing'],
    'delivery header differs from the signed timestamp' => [function (Request $request) {
        $request->headers->set('X-Lettermint-Delivery', (string) (time() - 1));

        return $request;
    }, 'delivery_timestamp_mismatch'],
    'tampered body' => [fn (Request $request, string $payload) => Request::create(
        '/lettermint/webhook', 'POST', [], [], [], $request->server->all(), str_replace('message.sent', 'message.failed', $payload),
    ), 'signature_mismatch'],
    'wrong secret' => [fn (Request $request, string $payload) => createSignedRequest($payload, 'other-secret'), 'signature_mismatch'],
]);

it('rejects a negative tolerance as a configuration error', function () {
    config()->set('lettermint.webhooks.secret', 'test-secret');
    config()->set('lettermint.webhooks.tolerance', -1);

    $request = createSignedRequest(json_encode(['event' => 'message.sent']), 'test-secret');

    (new VerifyWebhookSignature)->handle($request, fn () => response()->json(['passed' => true]));
})->throws(LettermintConfigException::class);

it('rejects invalid signature', function () {
    config()->set('lettermint.webhooks.secret', 'test-secret');
    config()->set('lettermint.webhooks.tolerance', 300);

    $payload = json_encode(['id' => 'test']);

    $request = Request::create(
        '/lettermint/webhook',
        'POST',
        [],
        [],
        [],
        [
            'HTTP_X_LETTERMINT_SIGNATURE' => 't=1234567890,v1=invalid',
            'HTTP_X_LETTERMINT_DELIVERY' => '1234567890',
            'CONTENT_TYPE' => 'application/json',
        ],
        $payload
    );

    $middleware = new VerifyWebhookSignature;
    $response = $middleware->handle($request, fn ($req) => response()->json(['passed' => true]));

    expect($response->getStatusCode())->toBe(401);
    expect($response->getContent())->toContain('Invalid signature');
});

it('throws exception when webhook secret is not configured', function () {
    config()->set('lettermint.webhooks.secret', null);

    $payload = json_encode(['id' => 'test']);
    $request = createSignedRequest($payload, 'any-secret');

    $middleware = new VerifyWebhookSignature;

    expect(fn () => $middleware->handle($request, fn ($req) => response()->json(['passed' => true])))
        ->toThrow(WebhookSecretNotFoundException::class);
});

it('rejects expired timestamp', function () {
    config()->set('lettermint.webhooks.secret', 'test-secret');
    config()->set('lettermint.webhooks.tolerance', 300);

    $payload = json_encode(['id' => 'test']);

    // Create request with old timestamp (10 minutes ago)
    $oldTimestamp = time() - 600;
    $request = createSignedRequest($payload, 'test-secret', $oldTimestamp);

    $middleware = new VerifyWebhookSignature;
    $response = $middleware->handle($request, fn ($req) => response()->json(['passed' => true]));

    expect($response->getStatusCode())->toBe(401);
});

it('uses custom tolerance from config', function () {
    config()->set('lettermint.webhooks.secret', 'test-secret');
    config()->set('lettermint.webhooks.tolerance', 1200); // 20 minutes

    $payload = json_encode([
        'id' => 'test-123',
        'event' => 'message.sent',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [],
    ]);

    // Create request with timestamp 10 minutes ago (within new tolerance)
    $tenMinutesAgo = time() - 600;
    $request = createSignedRequest($payload, 'test-secret', $tenMinutesAgo);

    $middleware = new VerifyWebhookSignature;
    $response = $middleware->handle($request, fn ($req) => response()->json(['passed' => true]));

    expect($response->getStatusCode())->toBe(200);
});
