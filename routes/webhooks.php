<?php

use Illuminate\Support\Facades\Route;
use Lettermint\Laravel\Webhooks\VerifyWebhookSignature;
use Lettermint\Laravel\Webhooks\WebhookController;

Route::post(
    config('lettermint.webhooks.prefix', 'lettermint').'/webhook',
    WebhookController::class
)->name('lettermint.webhook')
    ->middleware(VerifyWebhookSignature::class);
