<?php

use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;

it('can create a project token not found exception', function () {
    $exception = ApiTokenNotFoundException::create();

    expect($exception)
        ->toBeInstanceOf(ApiTokenNotFoundException::class)
        ->getMessage()->toBe('No Lettermint project token was found. Please set the LETTERMINT_PROJECT_TOKEN variable in your environment.');
});
