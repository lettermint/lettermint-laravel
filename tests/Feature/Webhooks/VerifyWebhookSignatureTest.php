<?php

use Illuminate\Http\Request;
use Lettermint\Laravel\Webhooks\Exceptions\WebhookSecretNotFoundException;
use Lettermint\Laravel\Webhooks\VerifyWebhookSignature;

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
    expect($request->attributes->get('lettermint_webhook_payload'))->toBe([
        'id' => 'test-123',
        'event' => 'message.sent',
        'timestamp' => '2024-01-01T00:00:00Z',
        'data' => [],
    ]);
});

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
