# Official Lettermint driver for Laravel

[![Latest Version on Packagist](https://img.shields.io/packagist/v/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/lettermint/lettermint-laravel/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/lettermint/lettermint-laravel/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/lettermint/lettermint-laravel.svg?style=flat-square)](https://packagist.org/packages/lettermint/lettermint-laravel)
[![Join our Discord server](https://img.shields.io/discord/1305510095588819035?logo=discord&logoColor=eee&label=Discord&labelColor=464ce5&color=0D0E28&cacheSeconds=43200)](https://lettermint.co/r/discord)

Send email from Laravel through [Lettermint](https://lettermint.co), use the Lettermint Team API, and receive verified webhook events. The package builds on the official [Lettermint PHP SDK](https://github.com/lettermint/lettermint-php) 3.0.

Upgrading from 2.x? Read [UPGRADE.md](UPGRADE.md).

## Requirements

- PHP 8.2 or later
- Laravel 10, 11, 12 or 13

## Installation

```bash
composer require lettermint/lettermint-laravel
```

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="lettermint-config"
```

This creates `config/lettermint.php`.

## Configuration

### Tokens

Lettermint has two kinds of API tokens. Add the ones you need to your `.env` file:

```env
# Sends email: the mail transport and Lettermint::emails()
LETTERMINT_PROJECT_TOKEN=lm_...

# The Team API: domains, messages, projects, routes, webhooks, ...
LETTERMINT_TEAM_TOKEN=lm_team_...
```

| Variable | Config key | Token | Used by |
| --- | --- | --- | --- |
| `LETTERMINT_PROJECT_TOKEN` | `lettermint.token` | Project sending token, from your project settings | The mail transport and `Lettermint::emails()` |
| `LETTERMINT_TEAM_TOKEN` | `lettermint.api_token` | Team API token | Every other part of the client |

Configure one token or both. Each part uses its own token and never falls back to the other one: with only a project token you can send email, and a Team API call throws a `Lettermint\Exceptions\LettermintConfigException` that names the missing token before any request is made.

The names used by earlier versions still work: `LETTERMINT_TOKEN` is read when `LETTERMINT_PROJECT_TOKEN` is not set, and `LETTERMINT_API_TOKEN` when `LETTERMINT_TEAM_TOKEN` is not set.

You can also set the tokens in `config/services.php`. Values in `config/lettermint.php` take precedence:

```php
'lettermint' => [
    'token' => env('LETTERMINT_PROJECT_TOKEN'),
    'api_token' => env('LETTERMINT_TEAM_TOKEN'),
],
```

### Request timeout

Requests time out after 15 seconds. The timeout covers the whole request, including reading the response. Change it in your `.env` file:

```env
LETTERMINT_TIMEOUT=30
```

## Sending email

### Set up the mailer

Add a mailer that uses the `lettermint` transport to `config/mail.php`:

```php
'mailers' => [
    'lettermint' => [
        'transport' => 'lettermint',
    ],
],
```

Then make it the default in your `.env` file:

```env
MAIL_MAILER=lettermint
```

Laravel mail works as usual:

```php
Mail::to($user)->send(new WelcomeMail($user));
```

### Routes

To send through a specific Lettermint route, add `route_id` to the mailer:

```php
'lettermint' => [
    'transport' => 'lettermint',
    'route_id' => env('LETTERMINT_ROUTE_ID'),
],
```

You can configure several mailers with different routes:

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

```php
Mail::mailer('lettermint_marketing')->to($user)->send(new MarketingEmail());
Mail::mailer('lettermint_transactional')->to($user)->send(new TransactionalEmail());
```

### Idempotency

An idempotency key makes retries safe: Lettermint processes a key once, so a retried queue job does not send the email twice.

Set a key on an email with the `Idempotency-Key` header. It is sent as a request header, not as an email header:

```php
Mail::send('emails.welcome', $data, function ($message) {
    $message->to('user@example.com')
        ->subject('Welcome!')
        ->getHeaders()->addTextHeader('Idempotency-Key', 'welcome-user-123');
});
```

Or let the transport derive a key from the email's content with the `idempotency` mailer option:

```php
'lettermint' => [
    'transport' => 'lettermint',
    'idempotency' => true,          // Default: false
    'idempotency_window' => 86400,  // Seconds; default: 24 hours
],
```

The automatic key is a hash of the subject, the recipients (to, cc, bcc), the body and the sender. With a window shorter than 24 hours, the key also includes the current time window, so the same email can be sent again in the next window. With the default window of 24 hours, which matches how long Lettermint keeps idempotency keys, identical emails are sent once.

An `Idempotency-Key` header always takes precedence. `'idempotency' => false` only disables the automatic key.

Each email gets its own key: the transport never reuses a key, or any other part of an email, for the next email.

### Tags and metadata

Tags and metadata use Laravel's mailable methods:

```php
Mail::send((new OrderShipped($order))
    ->tag('transactional')
    ->metadata('order_id', $order->id)
    ->metadata('tracking_number', $order->tracking_number)
);
```

Or in the mailable's envelope:

```php
use Illuminate\Mail\Mailables\Envelope;

public function envelope(): Envelope
{
    return new Envelope(
        subject: 'Your order has shipped!',
        tags: ['transactional'],
        metadata: [
            'order_id' => $this->order->id,
        ],
    );
}
```

Lettermint stores one tag per email; when a mailable has several, the last one is sent. The `X-LM-Tag` header is also supported, for consistency with the SMTP relay; a tag set with `tag()` takes precedence over it.

### Attachments

Attachments, including inline images referenced with `cid:`, are sent as they are. Every attachment needs a file name; the transport throws a `TransportException` before sending when one has none.

### Errors

When Lettermint rejects an email or cannot be reached, the transport throws Symfony's `TransportException`, as other Laravel mail drivers do. Its code is the HTTP status (`0` when no response was received), and `getPrevious()` returns the SDK exception with the details:

```php
use Lettermint\Exceptions\RateLimitException;
use Lettermint\Exceptions\ValidationException;
use Symfony\Component\Mailer\Exception\TransportException;

try {
    Mail::to($user)->send(new WelcomeMail($user));
} catch (TransportException $e) {
    $error = $e->getPrevious();

    if ($error instanceof ValidationException) {
        report($e); // $error->errors holds the field errors
    } elseif ($error instanceof RateLimitException) {
        // Retry after $error->retryAfter seconds, with the same idempotency key.
    }
}
```

See the [PHP SDK README](https://github.com/lettermint/lettermint-php#errors) for every exception class.

## Using the Lettermint client

The package registers one `Lettermint\Lettermint` client in the container, built from your configuration. It holds no message state, so the same instance serves the mail transport, queue workers and Octane. Use it through the facade, dependency injection or the container:

```php
use Lettermint\Laravel\Facades\Lettermint;

$result = Lettermint::emails()->send([
    'from' => 'Acme <hello@acme.com>',
    'to' => ['jane@example.com'],
    'subject' => 'Welcome to Acme',
    'html' => '<p>Thanks for signing up.</p>',
], idempotencyKey: "welcome-{$user->id}");

$result->message_id;
```

```php
use Lettermint\Lettermint;

class InvoiceMailer
{
    public function __construct(private Lettermint $lettermint) {}

    public function send(Invoice $invoice): void
    {
        $this->lettermint->emails->compose()
            ->from('billing@acme.com')
            ->to($invoice->customer->email)
            ->subject("Invoice {$invoice->number}")
            ->html(view('emails.invoice', ['invoice' => $invoice])->render())
            ->send(idempotencyKey: "invoice-{$invoice->id}");
    }
}
```

The facade exposes each part of the client as a method (`Lettermint::emails()`, `Lettermint::domains()`, …) and forwards the client's methods (`Lettermint::ping()`). On an injected client, the parts are properties (`$lettermint->emails`, `$lettermint->domains`).

### Team API

With `LETTERMINT_TEAM_TOKEN` configured:

```php
use Lettermint\Laravel\Facades\Lettermint;

$domains = Lettermint::domains()->list(['filter' => ['status' => 'verified']]);

foreach (Lettermint::messages()->iterate(['page' => ['size' => 100]]) as $message) {
    // Follows the cursor through every page.
}

$team = Lettermint::team()->retrieve();
```

The [PHP SDK README](https://github.com/lettermint/lettermint-php) documents every part of the client, the email builder, batch sending, scheduling, pagination and the response types.

### Queued jobs

The client refuses to be serialized because it holds your tokens. Don't store it in a property of a queued job, event or listener; resolve it where you use it, for example as a `handle()` argument:

```php
public function handle(Lettermint $lettermint): void
{
    $lettermint->emails->send($this->message);
}
```

### Custom HTTP client

To send the client's requests through your own Guzzle client, for example with a proxy, bind it before the client is first resolved:

```php
use GuzzleHttp\Client;
use Lettermint\Laravel\LettermintServiceProvider;

$this->app->bind(LettermintServiceProvider::HTTP_CLIENT, fn () => new Client(['proxy' => 'http://proxy.internal:3128']));
```

The SDK still disables redirects and applies the timeout to every request.

## Webhooks

The package receives Lettermint webhooks, verifies their signature with the PHP SDK, and dispatches a Laravel event for each delivery.

### Configuration

Add the webhook's signing secret, including its `whsec_` prefix, to your `.env` file:

```env
LETTERMINT_WEBHOOK_SECRET=whsec_...
```

Optionally change the route prefix and the timestamp tolerance (in seconds):

```env
LETTERMINT_WEBHOOK_PREFIX=lettermint
LETTERMINT_WEBHOOK_TOLERANCE=300
```

### Webhook endpoint

The package registers this endpoint, named `lettermint.webhook`:

```
POST /{prefix}/webhook
```

By default this is `POST /lettermint/webhook`. Add its URL to the webhook in your Lettermint dashboard.

Every request must carry the `X-Lettermint-Signature` and `X-Lettermint-Delivery` headers that Lettermint sends, and the raw body exactly as it was sent. If a proxy sits in front of your application, make sure it forwards both headers and does not change the body. A request that fails verification gets a `401` response whose `reason` says why, for example `signature_mismatch` or `timestamp_out_of_tolerance`, and no event is dispatched.

### Custom webhook routes

To register the endpoint yourself, for example on a specific domain, disable the automatic route:

```env
LETTERMINT_WEBHOOK_ENABLED=false
```

Then register the controller and the signature middleware in your routes file:

```php
use Illuminate\Support\Facades\Route;
use Lettermint\Laravel\Webhooks\VerifyWebhookSignature;
use Lettermint\Laravel\Webhooks\WebhookController;

Route::post(
    config('lettermint.webhooks.prefix', 'lettermint').'/webhook',
    WebhookController::class
)->name('lettermint.webhook')
    ->middleware(VerifyWebhookSignature::class);
```

You can wrap the registration in `Route::domain(...)` or another route group. Keeping the name and prefix means existing dashboard URLs and `route('lettermint.webhook')` calls keep working.

In your own controller, `VerifyWebhookSignature::payload($request)` returns the verified `Lettermint\WebhookPayload`.

### Handling webhook events

Listen to the events you need:

```php
use Illuminate\Support\Facades\Event;
use Lettermint\Laravel\Events\MessageDelivered;
use Lettermint\Laravel\Events\MessageHardBounced;

Event::listen(MessageDelivered::class, function (MessageDelivered $event) {
    Log::info('Email delivered', [
        'message_id' => $event->data->messageId,
        'recipient' => $event->data->recipient,
        'status_code' => $event->data->response->statusCode,
    ]);
});

Event::listen(MessageHardBounced::class, function (MessageHardBounced $event) {
    // A permanent bounce: consider disabling the recipient.
    $recipient = $event->data->recipient;
    $reason = $event->data->response->content;
});
```

### Available events

| Event class | Webhook type | Description |
| --- | --- | --- |
| `MessageCreated` | `message.created` | Message accepted for processing |
| `MessageSent` | `message.sent` | Message sent to the recipient's server |
| `MessageDelivered` | `message.delivered` | Message delivered |
| `MessageHardBounced` | `message.hard_bounced` | Permanent delivery failure |
| `MessageSoftBounced` | `message.soft_bounced` | Temporary delivery failure |
| `MessageSpamComplaint` | `message.spam_complaint` | Recipient reported spam |
| `MessageFailed` | `message.failed` | Processing failure |
| `MessageSuppressed` | `message.suppressed` | Message suppressed |
| `MessagePolicyRejected` | `message.policy_rejected` | Rejected by the sending policy |
| `MessageUnsubscribed` | `message.unsubscribed` | Recipient unsubscribed |
| `MessageOpened` | `message.opened` | Recipient opened the email |
| `MessageClicked` | `message.clicked` | Recipient clicked a link |
| `MessageInbound` | `message.inbound` | Inbound email received |
| `MessageAutoReplied` | `message.auto_replied` | An automatic reply was received |
| `MessageScheduled` | `message.scheduled` | A message was scheduled |
| `MessageRescheduled` | `message.rescheduled` | A message's schedule changed |
| `MessageCanceled` | `message.canceled` | A scheduled message was canceled |
| `MessageReleased` | `message.released` | A scheduled message was released |
| `SuppressionAdded` | `suppression.added` | Suppression entry added |
| `SuppressionRemoved` | `suppression.removed` | Suppression entry removed |
| `WebhookTest` | `webhook.test` | Test event from the dashboard |

All event classes are in the `Lettermint\Laravel\Events` namespace.

### Event structure

Each typed event has three properties:

```php
// The envelope, common to all events
$event->envelope->id;        // Delivery ID (string)
$event->envelope->event;     // WebhookEventType enum
$event->envelope->timestamp; // ?DateTimeImmutable

// The typed data, per event (here MessageDelivered)
$event->data->messageId;            // string
$event->data->recipient;            // string
$event->data->response->statusCode; // ?int
$event->data->response->content;    // ?string
$event->data->metadata;             // array
$event->data->tag;                  // ?string

// The complete verified payload, as decoded from the request
$event->payload['context'];      // e.g. ['scope' => 'project', 'project_id' => '…', …]
$event->payload['data']['tags']; // fields the typed data does not map
```

| Event | Data properties |
| --- | --- |
| `MessageCreated` | `messageId`, `from`, `to`, `cc`, `bcc`, `replyTo`, `subject`, `metadata`, `tag` |
| `MessageSent`, `MessageSpamComplaint` | `messageId`, `recipient`, `metadata`, `tag` |
| `MessageDelivered`, `MessageHardBounced`, `MessageSoftBounced` | `messageId`, `recipient`, `response`, `metadata`, `tag` |
| `MessageFailed` | `messageId`, `recipient`, `reason`, `response`, `metadata`, `tag` |
| `MessageSuppressed` | `messageId`, `recipient`, `reason`, `metadata`, `tag` |
| `MessagePolicyRejected` | `messageId`, `subject`, `reason`, `score`, `spamSymbols`, `metadata`, `tag` |
| `MessageUnsubscribed` | `messageId`, `recipient`, `unsubscribedAt`, `metadata`, `tag` |
| `MessageOpened` | `messageId`, `subject`, `recipient`, `openedAt`, `firstOpen`, `deviceType`, `clientType`, `clientName`, `userAgent`, `bot`, `metadata`, `tag` |
| `MessageClicked` | `messageId`, `subject`, `recipient`, `clickedAt`, `destinationUrl`, `linkIndex`, `anchorText`, `firstClick`, `deviceType`, `clientType`, `clientName`, `userAgent`, `bot`, `metadata`, `tag` |
| `MessageInbound` | `route`, `messageId`, `from`, `to`, `cc`, `recipient`, `subaddress`, `replyTo`, `subject`, `date`, `body`, `tag`, `headers`, `attachments`, `isSpam`, `spamScore`, `spamSymbols` |
| `MessageAutoReplied` | `messageId`, `subject`, `autoReply`, `metadata`, `tag` |
| `MessageScheduled`, `MessageRescheduled`, `MessageCanceled`, `MessageReleased` | `messageId`, `subject`, the schedule times, `metadata`, `tag` |
| `SuppressionAdded`, `SuppressionRemoved` | `suppressionId`, `type`, `value`, `reason`, `appliesTo` |
| `WebhookTest` | `message`, `webhookId`, `timestamp` |

The typed data tolerates changes to the payload: a field Lettermint omits, or sends with an unexpected type, reads as `null` (or an empty list) instead of failing the delivery, and fields the package does not know are ignored but stay available in `$event->payload`. Fields that Lettermint may omit or send as `null` are nullable; identifiers that every delivery carries, such as `messageId`, are strings.

Inbound attachments are delivered as base64 `content`, or as a signed `url` (with `expiresAt`) when the route is set to deliver attachment URLs. `$attachment->getDecodedContent()` returns the raw bytes, or `null` for a URL attachment.

### Unknown event types

When Lettermint sends an event type that your version of the package does not know yet, the delivery is still acknowledged with a `200` response, and an `UnknownWebhookEventReceived` event is dispatched with the event name and the complete verified payload. Update the package to receive a typed event instead.

```php
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;

Event::listen(UnknownWebhookEventReceived::class, function (UnknownWebhookEventReceived $event) {
    Log::info('Unhandled Lettermint webhook', [
        'type' => $event->event, // e.g. "message.some_new_event"
        'id' => $event->payload['id'] ?? null,
    ]);
});
```

### Listening to all events

Every webhook event implements the `Lettermint\Laravel\Contracts\WebhookEvent` interface, so one listener on it receives all of them: every typed event, and `UnknownWebhookEventReceived`.

```php
use Illuminate\Support\Facades\Event;
use Lettermint\Laravel\Contracts\WebhookEvent;
use Lettermint\Laravel\Events\LettermintWebhookEvent;
use Lettermint\Laravel\Events\UnknownWebhookEventReceived;

Event::listen(WebhookEvent::class, function (WebhookEvent $event) {
    if ($event instanceof UnknownWebhookEventReceived) {
        Log::info('Unknown webhook received', ['type' => $event->event]);

        return;
    }

    /** @var LettermintWebhookEvent $event */
    Log::info('Webhook received', [
        'type' => $event->getEnvelope()->event->value,
        'id' => $event->getEnvelope()->id,
    ]);
});
```

> [!NOTE]
> A listener on the abstract `LettermintWebhookEvent` class receives nothing: Laravel matches listeners on an event's own class and its interfaces, not its parent classes. Use the `WebhookEvent` interface.

### Helper methods

The `WebhookEventType` enum groups event types:

```php
$event->envelope->event->isBounce();        // true for hard and soft bounces
$event->envelope->event->isDeliveryIssue(); // true for bounces, failed, suppressed and policy-rejected messages
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
