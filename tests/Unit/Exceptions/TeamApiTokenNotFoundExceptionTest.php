<?php

use Lettermint\Laravel\Exceptions\TeamApiTokenNotFoundException;

it('can create an api token not found exception', function () {
    $exception = TeamApiTokenNotFoundException::create();

    expect($exception)
        ->toBeInstanceOf(TeamApiTokenNotFoundException::class)
        ->getMessage()->toBe('No Lettermint API token was found. Please set the LETTERMINT_API_TOKEN variable in your environment.');
});
