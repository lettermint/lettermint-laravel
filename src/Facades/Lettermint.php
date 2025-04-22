<?php

namespace Lettermint\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lettermint\Laravel\Lettermint
 */
class Lettermint extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lettermint\Laravel\Lettermint::class;
    }
}
