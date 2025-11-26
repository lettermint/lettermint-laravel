<?php

namespace Lettermint\Laravel\Webhooks\Exceptions;

use InvalidArgumentException;

final class WebhookSecretNotFoundException extends InvalidArgumentException
{
    public static function create(): self
    {
        return new self(
            'No Lettermint webhook secret was found. Please set the LETTERMINT_WEBHOOK_SECRET variable in your environment.'
        );
    }
}
