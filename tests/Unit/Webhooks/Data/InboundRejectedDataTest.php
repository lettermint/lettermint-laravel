<?php

use Lettermint\Laravel\Webhooks\Data\InboundRejectedData;

it('maps an inbound.rejected payload', function () {
    $data = InboundRejectedData::fromArray([
        'rejection_id' => '0f3c2b9e-5a4d-4c1b-9e8f-7a6b5c4d3e2f',
        'route' => 'support-inbox',
        'stage' => 'rcpt',
        'reason' => 'message_too_large',
        'permanent' => true,
        'recipients' => ['support@acme.com', 'help@acme.com'],
        'smtp' => ['code' => 550, 'enhanced_code' => '5.3.4', 'message' => 'Message size exceeds fixed maximum message size'],
        'envelope' => ['mail_from' => 'customer@example.com', 'remote_ip' => '203.0.113.42', 'remote_hostname' => 'mail.example.com', 'helo' => 'mail.example.com'],
        'size' => ['declared_bytes' => 52428800, 'observed_bytes' => null, 'limit_bytes' => 26214400],
    ]);

    expect($data->rejectionId)->toBe('0f3c2b9e-5a4d-4c1b-9e8f-7a6b5c4d3e2f')
        ->and($data->route)->toBe('support-inbox')
        ->and($data->stage)->toBe('rcpt')
        ->and($data->reason)->toBe('message_too_large')
        ->and($data->permanent)->toBeTrue()
        ->and($data->recipients)->toBe(['support@acme.com', 'help@acme.com'])
        ->and($data->smtp->code)->toBe(550)
        ->and($data->smtp->enhancedCode)->toBe('5.3.4')
        ->and($data->smtp->message)->toBe('Message size exceeds fixed maximum message size')
        ->and($data->envelope->mailFrom)->toBe('customer@example.com')
        ->and($data->envelope->remoteIp)->toBe('203.0.113.42')
        ->and($data->envelope->remoteHostname)->toBe('mail.example.com')
        ->and($data->envelope->helo)->toBe('mail.example.com')
        ->and($data->size?->declaredBytes)->toBe(52428800)
        ->and($data->size?->observedBytes)->toBeNull()
        ->and($data->size?->limitBytes)->toBe(26214400);
});

it('reads a rejection without size and with an empty sender', function () {
    $data = InboundRejectedData::fromArray([
        'rejection_id' => 'rejection-1',
        'route' => 'support-inbox',
        'stage' => 'data',
        'reason' => 'attachment_blocked',
        'permanent' => true,
        'recipients' => ['support@acme.com'],
        'smtp' => ['code' => 550, 'enhanced_code' => null, 'message' => 'Blocked attachment'],
        'envelope' => ['mail_from' => null, 'remote_ip' => '203.0.113.42', 'remote_hostname' => null, 'helo' => null],
        'size' => null,
    ]);

    expect($data->size)->toBeNull()
        ->and($data->smtp->enhancedCode)->toBeNull()
        ->and($data->envelope->mailFrom)->toBeNull()
        ->and($data->envelope->remoteHostname)->toBeNull();
});
