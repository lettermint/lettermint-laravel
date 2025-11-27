<?php

use Lettermint\Laravel\Webhooks\Data\MessageDeliveredData;

it('can create message delivered data from array', function () {
    $data = [
        'message_id' => 'msg-456',
        'recipient' => 'test@example.com',
        'response' => [
            'status_code' => 250,
            'enhanced_status_code' => '2.0.0',
            'content' => 'Message accepted',
        ],
        'metadata' => ['user_id' => '123'],
        'tag' => 'welcome',
    ];

    $payload = MessageDeliveredData::fromArray($data);

    expect($payload->messageId)->toBe('msg-456');
    expect($payload->recipient)->toBe('test@example.com');
    expect($payload->response->statusCode)->toBe(250);
    expect($payload->response->enhancedStatusCode)->toBe('2.0.0');
    expect($payload->response->content)->toBe('Message accepted');
    expect($payload->metadata)->toBe(['user_id' => '123']);
    expect($payload->tag)->toBe('welcome');
});

it('handles optional fields', function () {
    $data = [
        'message_id' => 'msg-456',
        'recipient' => 'test@example.com',
        'response' => [
            'status_code' => 250,
        ],
    ];

    $payload = MessageDeliveredData::fromArray($data);

    expect($payload->response->enhancedStatusCode)->toBeNull();
    expect($payload->response->content)->toBeNull();
    expect($payload->metadata)->toBe([]);
    expect($payload->tag)->toBeNull();
});
