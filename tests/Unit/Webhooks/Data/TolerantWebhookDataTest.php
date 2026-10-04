<?php

use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\MessageInbound;
use Lettermint\Laravel\Webhooks\Data\EmailAttachment;
use Lettermint\Laravel\Webhooks\Data\MessageClickedData;
use Lettermint\Laravel\Webhooks\Data\MessageCreatedData;
use Lettermint\Laravel\Webhooks\Data\MessageDeliveredData;
use Lettermint\Laravel\Webhooks\Data\MessageInboundData;
use Lettermint\Laravel\Webhooks\Data\MessagePolicyRejectedData;
use Lettermint\Laravel\Webhooks\Data\MessageReleasedData;
use Lettermint\Laravel\Webhooks\Data\SpamSymbol;
use Lettermint\Laravel\Webhooks\WebhookEventType;

it('builds every typed event from a payload without data', function (WebhookEventType $type, array $payload) {
    $event = $type->toEvent($payload);

    $eventClass = 'Lettermint\\Laravel\\Events\\'.$type->name;
    $dataClass = 'Lettermint\\Laravel\\Webhooks\\Data\\'.$type->name.'Data';

    expect($event)->toBeInstanceOf($eventClass)
        ->toBeInstanceOf(LettermintWebhookEvent::class)
        ->and($event->envelope->event)->toBe($type)
        ->and($event->data)->toBeInstanceOf($dataClass)
        ->and($event->payload)->toBe($payload);
})->with(function () {
    foreach (WebhookEventType::cases() as $type) {
        yield "{$type->value}, empty data" => [$type, ['id' => 'evt-1', 'event' => $type->value, 'timestamp' => '2026-10-04T08:00:00Z', 'data' => []]];
        yield "{$type->value}, no data" => [$type, ['event' => $type->value]];
        yield "{$type->value}, data is not an object" => [$type, ['id' => 'evt-1', 'event' => $type->value, 'data' => 'unexpected']];
        yield "{$type->value}, fields of the wrong type" => [$type, ['id' => 42, 'event' => $type->value, 'timestamp' => 'not a date', 'data' => [
            'message_id' => ['nested'], 'recipient' => null, 'subject' => false, 'metadata' => 'none', 'tag' => [],
            'response' => 'none', 'bot' => 'none', 'from' => 'a@example.com', 'to' => 'b@example.com', 'auto_reply' => 'none',
            'attachments' => ['not-an-object', ['filename' => null]], 'headers' => [null], 'spam_symbols' => [['name' => 'X']],
            'opened_at' => 'yesterday-ish', 'clicked_at' => 12, 'date' => [], 'unsubscribed_at' => null, 'link_index' => 'first',
            'score' => 'high', 'spam_score' => null, 'release_delay_seconds' => '60', 'timestamp' => 'now',
        ]]];
    }
});

it('reads missing optional fields as null and missing lists as empty', function () {
    $data = MessageCreatedData::fromArray(['message_id' => 'msg-1']);

    expect($data->messageId)->toBe('msg-1')
        ->and($data->from->email)->toBe('')
        ->and($data->from->name)->toBeNull()
        ->and($data->to)->toBe([])
        ->and($data->cc)->toBe([])
        ->and($data->bcc)->toBe([])
        ->and($data->replyTo)->toBe([])
        ->and($data->subject)->toBeNull()
        ->and($data->metadata)->toBe([])
        ->and($data->tag)->toBeNull();
});

it('accepts a single reply-to address as a string', function () {
    expect(MessageCreatedData::fromArray(['reply_to' => 'support@example.com'])->replyTo)->toBe(['support@example.com']);
});

it('ignores fields it does not know', function () {
    $data = MessageDeliveredData::fromArray([
        'message_id' => 'msg-1',
        'recipient' => 'jane@example.com',
        'response' => ['status_code' => 250, 'something_new' => true],
        'tags' => [['name' => 'campaign', 'value' => 'autumn']],
        'brand_new_field' => ['nested' => ['deep' => 1]],
    ]);

    expect($data->messageId)->toBe('msg-1')
        ->and($data->response->statusCode)->toBe(250);
});

it('coerces numeric strings and reads unparseable values as null', function () {
    $released = MessageReleasedData::fromArray(['release_delay_seconds' => '3600', 'scheduled_at' => '2026-10-04T08:00:00Z']);
    $clicked = MessageClickedData::fromArray(['link_index' => 'first', 'clicked_at' => 'not a date']);
    $rejected = MessagePolicyRejectedData::fromArray(['score' => '7.5']);

    expect($released->releaseDelaySeconds)->toBe(3600)
        ->and($released->releasedAt)->toBeNull()
        ->and($clicked->linkIndex)->toBeNull()
        ->and($clicked->clickedAt)->toBeNull()
        ->and($clicked->bot)->toBeNull()
        ->and($rejected->score)->toBe(7.5)
        ->and($rejected->subject)->toBeNull()
        ->and($rejected->reason)->toBeNull();
});

it('reads spam symbols with missing fields', function () {
    $symbol = SpamSymbol::fromArray(['name' => 'BAYES_SPAM']);

    expect($symbol->name)->toBe('BAYES_SPAM')
        ->and($symbol->score)->toBeNull()
        ->and($symbol->options)->toBe([])
        ->and($symbol->description)->toBeNull();
});

it('reads inbound attachments delivered as URLs', function () {
    $attachment = EmailAttachment::fromArray([
        'filename' => 'invoice.pdf',
        'content_type' => 'application/pdf',
        'size' => 1024,
        'content_id' => null,
        'url' => 'https://api.lettermint.co/v1/inbound/attachments/abc?signature=xyz',
        'expires_at' => '2026-11-01T00:00:00Z',
    ]);

    expect($attachment->content)->toBeNull()
        ->and($attachment->getDecodedContent())->toBeNull()
        ->and($attachment->url)->toBe('https://api.lettermint.co/v1/inbound/attachments/abc?signature=xyz')
        ->and($attachment->expiresAt)->toBe('2026-11-01T00:00:00Z')
        ->and($attachment->size)->toBe(1024);
});

it('builds an inbound event from a minimal payload', function () {
    $event = WebhookEventType::MessageInbound->toEvent([
        'id' => 'evt-1',
        'event' => 'message.inbound',
        'data' => [
            'route' => 'support',
            'message_id' => 'msg-1',
            'from' => ['email' => 'sender@example.com'],
            'attachments' => [['content_type' => 'message/rfc822', 'content' => base64_encode('raw')]],
        ],
    ]);

    expect($event)->toBeInstanceOf(MessageInbound::class);

    /** @var MessageInboundData $data */
    $data = $event->data;

    expect($data->route)->toBe('support')
        ->and($data->from->email)->toBe('sender@example.com')
        ->and($data->to)->toBe([])
        ->and($data->recipient)->toBeNull()
        ->and($data->subject)->toBeNull()
        ->and($data->date)->toBeNull()
        ->and($data->body->text)->toBeNull()
        ->and($data->spamScore)->toBeNull()
        ->and($data->isSpam)->toBeFalse()
        ->and($data->attachments[0]->filename)->toBe('attachment.eml')
        ->and($data->attachments[0]->getDecodedContent())->toBe('raw')
        ->and($data->attachments[0]->size)->toBeNull();
});
