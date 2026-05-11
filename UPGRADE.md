# Upgrade Guide

## Upgrading From v1 to v2

Lettermint Laravel v2 is a breaking release. It uses `lettermint/lettermint-php` v2 and separates the project token used for sending email from the API token used for the Team API.

### 1. Update Composer

```bash
composer require lettermint/lettermint-laravel:^2.0
```

The Laravel driver now requires:

```json
"lettermint/lettermint-php": "^2.0"
```

### 2. Update Environment Variables

For sending email through Laravel mail, prefer the project token variable:

```env
LETTERMINT_PROJECT_TOKEN=your-lettermint-project-token
```

The old `LETTERMINT_TOKEN` variable is still accepted by the default config fallback:

```php
'token' => env('LETTERMINT_PROJECT_TOKEN', env('LETTERMINT_TOKEN')),
```

For Team API access, add a separate API token:

```env
LETTERMINT_API_TOKEN=your-lettermint-api-token
```

### 3. Update Published Config

If you published `config/lettermint.php`, add the new `api_token` key:

```php
return [
    'token' => env('LETTERMINT_PROJECT_TOKEN', env('LETTERMINT_TOKEN')),

    'api_token' => env('LETTERMINT_API_TOKEN'),

    'webhooks' => [
        'secret' => env('LETTERMINT_WEBHOOK_SECRET'),
        'prefix' => env('LETTERMINT_WEBHOOK_PREFIX', 'lettermint'),
        'tolerance' => env('LETTERMINT_WEBHOOK_TOLERANCE', 300),
    ],
];
```

If you configure Lettermint through `config/services.php`, add the API token there too:

```php
'lettermint' => [
    'token' => env('LETTERMINT_PROJECT_TOKEN', env('LETTERMINT_TOKEN')),
    'api_token' => env('LETTERMINT_API_TOKEN'),
],
```

### 4. Sending Email

Laravel mail usage is unchanged:

```php
Mail::to($user)->send(new WelcomeMail());
```

The mail transport uses `LETTERMINT_PROJECT_TOKEN`.

### 5. Team API Access

Use the PHP SDK API client from Laravel's container:

```php
use Lettermint\Client\ApiClient;

$projects = app(ApiClient::class)->projects->list();
$team = app('lettermint.api')->team->retrieve();
```

The Team API client uses `LETTERMINT_API_TOKEN`.

### 6. Facade and Container Changes

The `Lettermint` facade resolves to the email endpoint for low-level sending:

```php
Lettermint::from('hello@example.com')
    ->to('user@example.com')
    ->subject('Hello')
    ->html('<p>Hello</p>')
    ->send();
```

The full Team API client is available through `Lettermint\Client\ApiClient::class` and the `lettermint.api` container alias.
