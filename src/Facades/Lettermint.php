<?php

namespace Lettermint\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use Lettermint\Endpoints\EmailEndpoint;

/**
 * @see \Lettermint\Endpoints\EmailEndpoint
 */
class Lettermint extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return EmailEndpoint::class;
    }
}
