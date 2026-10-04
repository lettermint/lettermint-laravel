<?php

use Lettermint\Laravel\Exceptions\ApiTokenNotFoundException;

it('can create a project token not found exception', function () {
    $exception = ApiTokenNotFoundException::create();

    expect($exception)
        ->toBeInstanceOf(ApiTokenNotFoundException::class)
        ->getMessage()->toBe('No Lettermint project token was found. Please set the LETTERMINT_PROJECT_TOKEN variable in your environment.');
});

it('can create a no tokens exception', function () {
    $exception = ApiTokenNotFoundException::noTokens();

    expect($exception)
        ->toBeInstanceOf(ApiTokenNotFoundException::class)
        ->getMessage()->toBe('No Lettermint token was found. Please set LETTERMINT_PROJECT_TOKEN to send email, LETTERMINT_TEAM_TOKEN to use the Team API, or both.');
});
