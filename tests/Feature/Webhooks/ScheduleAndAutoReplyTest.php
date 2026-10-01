<?php

use Illuminate\Support\Facades\Event;
use Lettermint\Laravel\Events\MessageAutoReplied;
use Lettermint\Laravel\Events\MessageCanceled;
use Lettermint\Laravel\Events\MessageReleased;
use Lettermint\Laravel\Events\MessageRescheduled;
use Lettermint\Laravel\Events\MessageScheduled;
use Lettermint\Laravel\Webhooks\WebhookEventType;

it('dispatches each current lifecycle event after signature verification', function (string $name, string $class, array $fields) {
    config()->set('lettermint.webhooks.secret', 'test-webhook-secret');
    Event::fake();
    $data = array_merge(['message_id' => 'msg-123', 'subject' => null, 'metadata' => [], 'tag' => null], $fields);
    $payload = json_encode(['id' => 'event-123', 'event' => $name, 'timestamp' => '2026-10-01T12:00:00Z', 'data' => $data], JSON_THROW_ON_ERROR);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'test-webhook-secret');

    $this->call('POST', route('lettermint.webhook'), [], [], [], [
        'HTTP_X_LETTERMINT_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'HTTP_X_LETTERMINT_DELIVERY' => (string) $timestamp,
        'CONTENT_TYPE' => 'application/json',
    ], $payload)->assertOk();

    Event::assertDispatched($class, function ($event) use ($fields, $name) {
        expect($event->data->messageId)->toBe('msg-123')
            ->and($event->data->subject)->toBeNull()
            ->and($event->envelope->event->value)->toBe($name);
        foreach ($fields as $key => $value) {
            $property = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $key))));
            if ($key === 'auto_reply') {
                expect($event->data->autoReply->sender)->toBe($value['sender'])
                    ->and($event->data->autoReply->body?->text)->toBe($value['body']['text'] ?? null);
            } else {
                expect($event->data->$property)->toBe($value);
            }
        }

        return true;
    });
})->with([
    ['message.scheduled', MessageScheduled::class, ['scheduled_at' => '2026-10-02T12:00:00Z']],
    ['message.rescheduled', MessageRescheduled::class, ['scheduled_at' => '2026-10-03T12:00:00Z', 'previous_scheduled_at' => '2026-10-02T12:00:00Z']],
    ['message.canceled', MessageCanceled::class, ['scheduled_at' => '2026-10-02T12:00:00Z', 'canceled_at' => '2026-10-01T12:00:00Z']],
    ['message.released', MessageReleased::class, ['scheduled_at' => '2026-10-01T11:00:00Z', 'released_at' => '2026-10-01T12:00:00Z', 'release_delay_seconds' => 3600]],
    ['message.auto_replied', MessageAutoReplied::class, ['auto_reply' => ['sender' => 'sender@example.com', 'recipient' => null, 'subject' => null, 'body' => ['text' => 'Away']]]],
    ['message.auto_replied', MessageAutoReplied::class, ['auto_reply' => ['sender' => null, 'recipient' => null, 'subject' => null]]],
]);

it('declares all 21 current webhook event names', function () {
    expect(array_column(WebhookEventType::cases(), 'value'))->toHaveCount(21);
});
