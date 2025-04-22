# Official Lettermint driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)

Integrate Lettermint 

## Installation

You can install the package via composer:

```bash
composer require lettermint/lettermint-laravel
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="lettermint-laravel-config"
```


## Usage

```php
$lettermint = new Lettermint\Lettermint();
echo $lettermint->echoPhrase('Hello, Lettermint!');
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
