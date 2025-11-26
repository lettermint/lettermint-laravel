<?php

use Lettermint\Laravel\Webhooks\Exceptions\WebhookSecretNotFoundException;

it('can create a webhook secret not found exception', function () {
    $exception = WebhookSecretNotFoundException::create();

    expect($exception)->toBeInstanceOf(WebhookSecretNotFoundException::class);
    expect($exception)->toBeInstanceOf(InvalidArgumentException::class);
    expect($exception->getMessage())->toBe(
        'No Lettermint webhook secret was found. Please set the LETTERMINT_WEBHOOK_SECRET variable in your environment.'
    );
});
