<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Lettermint Token
    |--------------------------------------------------------------------------
    |
    | Every Lettermint project has a unique API token. You can find your API
    | token in your Lettermint project settings.
    |
    */

    'token' => env('LETTERMINT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Webhooks Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the webhook endpoint settings for receiving events from
    | Lettermint. You can find your webhook signing secret in your
    | Lettermint project settings under the Webhooks section.
    |
    */

    'webhooks' => [

        /*
        |--------------------------------------------------------------------------
        | Webhook Secret
        |--------------------------------------------------------------------------
        |
        | The signing secret used to verify incoming webhook requests. This
        | ensures that the webhook payload is from Lettermint and hasn't
        | been tampered with.
        |
        */

        'secret' => env('LETTERMINT_WEBHOOK_SECRET'),

        /*
        |--------------------------------------------------------------------------
        | Webhook Route Prefix
        |--------------------------------------------------------------------------
        |
        | The route prefix for the webhook endpoint. The full URL will be:
        | {your-app-url}/{prefix}/webhook
        |
        | Default: lettermint (results in /lettermint/webhook)
        |
        */

        'prefix' => env('LETTERMINT_WEBHOOK_PREFIX', 'lettermint'),

        /*
        |--------------------------------------------------------------------------
        | Timestamp Tolerance
        |--------------------------------------------------------------------------
        |
        | The maximum allowed time difference (in seconds) between the webhook
        | timestamp and the current time. This helps prevent replay attacks.
        |
        | Default: 300 (5 minutes)
        |
        */

        'tolerance' => env('LETTERMINT_WEBHOOK_TOLERANCE', 300),

    ],

];
