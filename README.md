# Official Lettermint driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)

Easily integrate [Lettermint](https://lettermint.co) into your Laravel application.

---

## Requirements

- PHP 8.2 or higher
- Laravel 9 or higher


## Installation

You can install the package via composer:

```bash
composer require lettermint/lettermint-laravel
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="lettermint-config"
```

This creates a `config/lettermint.php` file where you can add your API token.

## Configuration

### Setting your API token

Add your Lettermint API token in your `.env` file:

```env
LETTERMINT_TOKEN=your-lettermint-token
```

Or update the `config/lettermint.php` file as needed.

### Add the transport

In your `config/mail.php`, set the default option to lettermint:
```php
        'lettermint' => [
            'transport' => 'lettermint',
        ],
```

### Add the service

In your `config/services.php`, add the Lettermint service:
```php
    'lettermint' => [
        'token' => env('LETTERMINT_TOKEN'),
    ],
```

### Using Routes

If you would like to specify the Lettermint route that should be used by a given mailer, you may add the `route_id` configuration option to the mailer's configuration array in your `config/mail.php` file:

```php
'lettermint' => [
    'transport' => 'lettermint',
    'route_id' => env('LETTERMINT_ROUTE_ID'),
],
```

### Multiple mailers with different routes

You can configure multiple mailers using the same Lettermint transport but with different route IDs:

```php
// config/mail.php
'mailers' => [
    'lettermint_marketing' => [
        'transport' => 'lettermint',
        'route_id' => env('LETTERMINT_MARKETING_ROUTE_ID'),
    ],
    'lettermint_transactional' => [
        'transport' => 'lettermint',
        'route_id' => env('LETTERMINT_TRANSACTIONAL_ROUTE_ID'),
    ],
],
```

Then use them in your application:
```php
Mail::mailer('lettermint_marketing')->to($user)->send(new MarketingEmail());
Mail::mailer('lettermint_transactional')->to($user)->send(new TransactionalEmail());
```

## Idempotency Support

The Lettermint Laravel driver prevents duplicate email sends by using idempotency keys. This is especially useful when emails are sent from queued jobs that might be retried.

### Configuration Options

You can configure idempotency behavior per mailer in your `config/mail.php`:

```php
'mailers' => [
    'lettermint' => [
        'transport' => 'lettermint',
        'idempotency' => true, // Enable automatic content-based idempotency
        'idempotency_window' => 86400, // Window in seconds (default: 24 hours)
    ],
    'lettermint_marketing' => [
        'transport' => 'lettermint',
        'route_id' => 'marketing',
        'idempotency' => false, // Disable automatic idempotency
    ],
],
```

#### Idempotency Options:

- **`idempotency`**: Enable/disable automatic content-based idempotency
  - `true`: Generates idempotency keys based on email content
  - `false` (default): Disables automatic idempotency (user headers still work)
- **`idempotency_window`**: Time window in seconds for deduplication
  - Default: `86400` (24 hours to match Lettermint API retention)
  - Set to match your needs (e.g., `3600` for 1 hour, `300` for 5 minutes)
  - When set to `86400` or higher, emails with identical content are permanently deduplicated within the API retention period

### Automatic Idempotency

When `idempotency` is `true`, the driver generates a unique key based on:
- Email subject, recipients (to, cc, bcc), and content
- Sender address (to differentiate between different sending contexts)
- Time window (if less than 24 hours)

This ensures:
- Identical emails are only sent once within the configured time window
- Retried queue jobs won't create duplicate emails
- Different emails or the same email after the time window will be sent normally

### Custom Idempotency Keys

You can override any configuration by setting a custom idempotency key in the email headers:

```php
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
        ->subject('Welcome!')
        ->getHeaders()->addTextHeader('Idempotency-Key', 'welcome-user-123');
});
```

**Priority order** (highest to lowest):
1. `Idempotency-Key` header in the email (always respected, overrides any config)
2. Automatic Message-ID (if `idempotency` is `true`)
3. No idempotency (if `idempotency` is `false`)

**Important:** The `idempotency: false` configuration only disables *automatic* idempotency. User-provided `Idempotency-Key` headers are always respected, giving users full control on a per-email basis.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Bjarn Bronsveld](https://github.com/bjarn)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
