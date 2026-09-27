<?php

use Illuminate\Support\Facades\Route;

it('does not register the webhook route when webhooks are disabled', function () {
    expect(config('lettermint.webhooks.enabled'))->toBeFalse()
        ->and(Route::has('lettermint.webhook'))->toBeFalse();
});
