<?php

use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;

it('can create an API token not found exception', function () {
    $exception = ApiTokenNotFoundException::create();

    expect($exception)
        ->toBeInstanceOf(ApiTokenNotFoundException::class)
        ->getMessage()->toBe('No Lettermint API token was found. Please set the LETTERMINT_TOKEN variable in your environment.');
});
