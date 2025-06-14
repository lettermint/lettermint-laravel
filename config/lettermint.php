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
    | Route ID
    |--------------------------------------------------------------------------
    |
    | The route ID to use for sending emails. This allows you to segment
    | emails and track them separately in your Lettermint dashboard.
    |
    */

    'route_id' => env('LETTERMINT_ROUTE_ID'),

];
