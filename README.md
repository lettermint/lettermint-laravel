# Official Lettermint driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![Join our Discord server](https://img.shields.io/discord/1305510095588819035?logo=discord&logoColor=eee&label=Discord&labelColor=464ce5&color=0D0E28&cacheSeconds=43200)](https://lettermint.co/r/discord)

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

## Tags and Metadata

The Lettermint Laravel driver supports adding tags and metadata to your emails for better organization, tracking, and analytics.

### Using Tags

Tags help you categorize and filter your emails in the Lettermint dashboard. You can add tags using Laravel's native mailable methods:

#### Method 1: Using Laravel's tag() method (Recommended)

```php
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;

Mail::send((new WelcomeEmail($user))
    ->tag('onboarding')
);
```

#### Method 2: Using the envelope method in your Mailable

```php
use Illuminate\Mail\Mailables\Envelope;

class WelcomeEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to our platform!',
            tags: ['onboarding'], // Only one tag is allowed
        );
    }
}
```

#### Method 3: Using custom header (backward compatibility)

To minimise confusion with the way of tagging emails sent via the SMTP relay, the Lettermint Laravel driver also supports the `X-LM-Tag` header.
This will be converted to the `TagHeader` envelope method automatically.

```php
use Illuminate\Mail\Mailables\Headers;

class WelcomeEmail extends Mailable
{
    public function headers(): Headers
    {
        return new Headers(
            text: [
                'X-LM-Tag' => 'onboarding',
            ],
        );
    }
}
```

### Using Metadata

Metadata allows you to attach custom key-value pairs to your emails for enhanced tracking and analytics:

#### Method 1: Using Laravel's metadata() method (Recommended)

```php
Mail::send((new OrderConfirmation($order))
    ->metadata('order_id', $order->id)
    ->metadata('customer_id', $order->customer_id)
);
```

#### Method 2: Using the envelope method

```php
public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Order Confirmation',
        metadata: [
            'order_id' => $this->order->id,
            'customer_id' => $this->order->customer_id,
            'order_total' => $this->order->total,
        ],
    );
}
```

### Combining Tags and Metadata

You can use both tags and metadata together:

```php
Mail::send((new OrderShipped($order))
    ->tag('transactional')
    ->metadata('order_id', $order->id)
    ->metadata('tracking_number', $order->tracking_number)
);
```

Or in your mailable:

```php
public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Your order has shipped!',
        tags: ['transactional', 'shipping'],
        metadata: [
            'order_id' => $this->order->id,
            'tracking_number' => $this->order->tracking_number,
            'carrier' => $this->order->carrier,
        ],
    );
}
```

### Note on Compatibility

- The driver supports Laravel's native `tag()` and `metadata()` methods (Laravel 9+)
- The `X-LM-Tag` header is supported for backward compatibility
- When both `TagHeader` and `X-LM-Tag` are present, the `TagHeader` takes precedence

## Webhooks

The package provides built-in support for handling Lettermint webhooks with automatic signature verification.

### Configuration

Add your webhook signing secret to your `.env` file:

```env
LETTERMINT_WEBHOOK_SECRET=your-webhook-signing-secret
```

You can optionally configure the route prefix and timestamp tolerance:

```env
LETTERMINT_WEBHOOK_PREFIX=lettermint
LETTERMINT_WEBHOOK_TOLERANCE=300
```

Or publish the config file and modify the webhooks section:

```php
// config/lettermint.php
'webhooks' => [
    'secret' => env('LETTERMINT_WEBHOOK_SECRET'),
    'prefix' => env('LETTERMINT_WEBHOOK_PREFIX', 'lettermint'),
    'tolerance' => env('LETTERMINT_WEBHOOK_TOLERANCE', 300),
],
```

### Webhook Endpoint

The package automatically registers a webhook endpoint at:

```
POST /{prefix}/webhook
```

By default, this is `POST /lettermint/webhook`. Configure this URL in your Lettermint dashboard.

### Handling Webhook Events

The package dispatches Laravel events for each webhook type. Listen to specific events in your `EventServiceProvider` or using closures:

```php
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageHardBounced;
use Lettermint\Laravel\Events\MessageSpamComplaint;

// In EventServiceProvider
protected $listen = [
    MessageDelivered::class => [
        HandleEmailDelivered::class,
    ],
    MessageHardBounced::class => [
        HandleEmailBounced::class,
    ],
];

// Or using closures
Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    Log::info('Email delivered', [
        'message_id' => $event->data->messageId,
        'recipient' => $event->data->recipient,
        'status_code' => $event->data->response->statusCode,
    ]);
});

Event::listen(MessageHardBounced::class, function (MessageHardBounced $event) {
    // Handle permanent bounce - consider disabling the recipient
    $recipient = $event->data->recipient;
    $reason = $event->data->response->content;
});
```

### Available Events

| Event Class            | Webhook Type             | Description                      |
|------------------------|--------------------------|----------------------------------|
| `MessageCreated`       | `message.created`        | Message accepted for processing  |
| `MessageSent`          | `message.sent`           | Message sent to recipient server |
| `MessageDelivered`     | `message.delivered`      | Message successfully delivered   |
| `MessageHardBounced`   | `message.hard_bounced`   | Permanent delivery failure       |
| `MessageSoftBounced`   | `message.soft_bounced`   | Temporary delivery failure       |
| `MessageSpamComplaint` | `message.spam_complaint` | Recipient reported spam          |
| `MessageFailed`        | `message.failed`         | Processing failure               |
| `MessageSuppressed`    | `message.suppressed`     | Message suppressed               |
| `MessageUnsubscribed`  | `message.unsubscribed`   | Recipient unsubscribed           |
| `MessageInbound`       | `message.inbound`        | Inbound email received           |
| `WebhookTest`          | `webhook.test`           | Test event from dashboard        |

### Listening to All Events

You can listen to all webhook events using the base class:

```php
use Lettermint\Laravel\Events\LettermintWebhookEvent;

Event::listen(LettermintWebhookEvent::class, function (LettermintWebhookEvent $event) {
    Log::info('Webhook received', [
        'type' => $event->getEnvelope()->event->value,
        'id' => $event->getEnvelope()->id,
    ]);
});
```

### Event Structure

Each event has two main properties:

- `$event->envelope` - Common webhook envelope (id, event type, timestamp)
- `$event->data` - Event-specific typed payload

```php
// Envelope (common to all events)
$event->envelope->id;        // Webhook event ID (string)
$event->envelope->event;     // WebhookEventType enum
$event->envelope->timestamp; // DateTimeImmutable

// Data (typed per event)
// For MessageDelivered:
$event->data->messageId;              // string
$event->data->recipient;              // string
$event->data->response->statusCode;   // int
$event->data->response->content;      // string|null
$event->data->metadata;               // array
$event->data->tag;                    // string|null
```

### Typed Event Data

Each event type has its own typed data class:

| Event                | Data Properties                                                                                        |
|----------------------|--------------------------------------------------------------------------------------------------------|
| `MessageDelivered`   | `messageId`, `recipient`, `response`, `metadata`, `tag`                                                |
| `MessageHardBounced` | `messageId`, `recipient`, `response`, `metadata`, `tag`                                                |
| `MessageCreated`     | `messageId`, `from`, `to`, `cc`, `bcc`, `subject`, `metadata`, `tag`                                   |
| `MessageInbound`     | `route`, `messageId`, `from`, `to`, `subject`, `body`, `headers`, `attachments`, `isSpam`, `spamScore` |
| `WebhookTest`        | `message`, `webhookId`, `timestamp`                                                                    |

### Helper Methods

The `WebhookEventType` enum provides helper methods:

```php
$event->envelope->event->isBounce();        // true for hard/soft bounces
$event->envelope->event->isDeliveryIssue(); // true for bounces, failed, suppressed
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
