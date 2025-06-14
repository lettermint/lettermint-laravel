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

Add your Lettermint API credentials in your `.env` file:

```env
LETTERMINT_TOKEN=your-lettermint-token
LETTERMINT_ROUTE_ID=your-route-id
```

Or update the `config/lettermint.php` file as needed.

### Add the transport

In your `config/mail.php`, add the Lettermint transport:
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
    ],
    'lettermint_transactional' => [
        'transport' => 'lettermint',
    ],
],

// config/services.php
'lettermint_marketing' => [
    'token' => env('LETTERMINT_TOKEN'),
    'route_id' => env('LETTERMINT_MARKETING_ROUTE_ID'),
],
'lettermint_transactional' => [
    'token' => env('LETTERMINT_TOKEN'),
    'route_id' => env('LETTERMINT_TRANSACTIONAL_ROUTE_ID'),
],
```

Then use them in your application:
```php
Mail::mailer('lettermint_marketing')->to($user)->send(new MarketingEmail());
Mail::mailer('lettermint_transactional')->to($user)->send(new TransactionalEmail());
```

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
