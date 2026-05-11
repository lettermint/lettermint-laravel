<?php

namespace Lettermint\Laravel\Exceptions;

use InvalidArgumentException;

final class ApiTokenNotFoundException extends InvalidArgumentException
{
    public static function create(): self
    {
        return new self(
            'No Lettermint project token was found. Please set the LETTERMINT_PROJECT_TOKEN variable in your environment.'
        );
    }
}
