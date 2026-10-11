<?php

use Lettermint\Laravel\Webhooks\Data\EmailHeader;
use Lettermint\Laravel\Webhooks\Data\MessageDeliveredData;
use Lettermint\Laravel\Webhooks\Data\MessageInboundData;
use Lettermint\Laravel\Webhooks\Data\MessageSentData;
use Lettermint\Laravel\Webhooks\Data\ServerResponse;
use Lettermint\Laravel\Webhooks\WebhookEventType;

dataset('outbound message events', function () {
    foreach (WebhookEventType::cases() as $type) {
        if (str_starts_with($type->value, 'message.') && $type !== WebhookEventType::MessageInbound) {
            yield $type->value => [$type];
        }
    }
});

function outboundMessageData(WebhookEventType $type, array $data): object
{
    return $type->toEvent(['id' => 'evt-1', 'event' => $type->value, 'data' => ['message_id' => 'msg-1', ...$data]])->data;
}

it('reads headers on every outbound message event', function (WebhookEventType $type) {
    $data = outboundMessageData($type, ['headers' => [
        ['name' => 'X-Correlation-ID', 'value' => 'correlation-id'],
        ['name' => 'X-Custom', 'value' => 'first'],
        ['name' => 'X-Custom', 'value' => 'second'],
    ]]);

    expect($data->headers)->toHaveCount(3)
        ->each->toBeInstanceOf(EmailHeader::class)
        ->and($data->headers[0]->name)->toBe('X-Correlation-ID')
        ->and($data->headers[0]->value)->toBe('correlation-id')
        ->and($data->headers[1]->name)->toBe('X-Custom')
        ->and($data->headers[2]->value)->toBe('second');
})->with('outbound message events');

it('reads an empty headers list on every outbound message event', function (WebhookEventType $type) {
    expect(outboundMessageData($type, ['headers' => []])->headers)->toBe([]);
})->with('outbound message events');

it('reads absent headers as an empty list on every outbound message event', function (WebhookEventType $type) {
    expect(outboundMessageData($type, [])->headers)->toBe([]);
})->with('outbound message events');

it('skips header entries that are not objects and tolerates a header list of the wrong type', function () {
    $data = MessageDeliveredData::fromArray(['headers' => ['nope', null, ['name' => 'X-Ok', 'value' => '1']]]);

    expect($data->headers)->toHaveCount(1)
        ->and($data->headers[0]->name)->toBe('X-Ok')
        ->and(MessageDeliveredData::fromArray(['headers' => 'none'])->headers)->toBe([]);
});

it('keeps existing constructor calls working without headers', function () {
    $positional = new MessageSentData('msg-1', 'jane@example.com', ['user_id' => '1'], 'welcome');
    $named = new MessageDeliveredData(
        messageId: 'msg-1',
        recipient: 'jane@example.com',
        response: ServerResponse::fromArray(['status_code' => 250]),
        metadata: [],
        tag: null,
    );
    $withHeaders = new MessageSentData('msg-1', 'jane@example.com', [], null, [new EmailHeader('X-Custom', 'a')]);

    expect($positional->headers)->toBe([])
        ->and($named->headers)->toBe([])
        ->and($withHeaders->headers[0]->name)->toBe('X-Custom');
});

it('keeps reading inbound headers', function () {
    $data = MessageInboundData::fromArray(['headers' => [['name' => 'X-Custom', 'value' => 'a']]]);

    expect($data->headers[0])->toBeInstanceOf(EmailHeader::class)
        ->and($data->headers[0]->value)->toBe('a');
});
