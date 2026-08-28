<?php

use Illuminate\Support\Facades\Route;

it('registers the webhook route by default', function () {
    expect(config('lettermint.webhooks.enabled'))->toBeTrue()
        ->and(Route::has('lettermint.webhook'))->toBeTrue()
        ->and(route('lettermint.webhook'))->toContain('/lettermint/webhook');
});
