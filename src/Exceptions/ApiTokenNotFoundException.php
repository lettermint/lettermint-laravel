<?php

namespace Lettermint\Laravel\Exceptions;

use InvalidArgumentException;

final class ApiTokenNotFoundException extends InvalidArgumentException
{
    /**
     * The lettermint mail transport needs the project sending token.
     */
    public static function create(): self
    {
        return new self(
            'No Lettermint project token was found. Please set the LETTERMINT_PROJECT_TOKEN variable in your environment.'
        );
    }

    /**
     * The Lettermint client needs at least one token.
     */
    public static function noTokens(): self
    {
        return new self(
            'No Lettermint token was found. Please set LETTERMINT_PROJECT_TOKEN to send email, LETTERMINT_TEAM_TOKEN to use the Team API, or both.'
        );
    }
}
