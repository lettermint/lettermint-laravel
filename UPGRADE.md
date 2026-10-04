# Upgrade Guide

- [Upgrade from 2.x to 3.0](#upgrade-from-2x-to-30)
- [Upgrade from v1 to v2](#upgrading-from-v1-to-v2)

## Upgrade from 2.x to 3.0

2.x no longer receives updates, including fixes. Upgrade to 3.0 to keep getting them.

3.0 moves the package to the [Lettermint PHP SDK 3.0](https://github.com/lettermint/lettermint-php/blob/main/UPGRADE.md). The main changes:

- **One client.** The container binds one `Lettermint\Lettermint` client, built from your project token, your team token or both. It replaces the `EmailEndpoint` and `ApiClient` bindings, and the `Lettermint` facade now resolves to it: `Lettermint::emails()->send([...])`, `Lettermint::domains()->list()`.
- **Stateless sending.** The SDK keeps no message on the client, so nothing about one email can leak into the next one, also in queue workers and Octane. Idempotency keys are a per-call argument.
- **Typed errors.** A failed send throws a `TransportException` whose code is the HTTP status and whose `getPrevious()` is a typed SDK exception.
- **Webhooks.** Verification uses the SDK 3.0 `Webhook`, which requires the `X-Lettermint-Delivery` header that Lettermint sends with every delivery. Webhook data objects tolerate missing, extra and changed fields, and each event carries the complete payload.

Laravel mail usage (`Mail::to(...)->send(...)`), the mailer options (`route_id`, `idempotency`, `idempotency_window`), the webhook route and the event classes are unchanged.

### Requirements

| | 2.x | 3.0 |
| --- | --- | --- |
| PHP | 8.2 or later | 8.2 or later (unchanged) |
| Laravel | 10 to 13 | 10 to 13 (unchanged) |
| `lettermint/lettermint-php` | `^2.1` | `^3.0` |

### Upgrade with a coding agent

You can let a coding agent (Claude Code, Codex, Cursor, Copilot, …) do the upgrade. Copy this instruction into the agent from your project's root, then review its changes:

````text
Upgrade this Laravel project from `lettermint/lettermint-laravel` 2.x to 3.0.

1. Run `composer require lettermint/lettermint-laravel:^3.0 -W`. If composer.json also requires `lettermint/lettermint-php` directly, change that constraint to `^3.0` in the same command. 3.0 needs PHP 8.2 or newer and Laravel 10 to 13: check `composer.json`, CI workflows and Dockerfiles, and report anything older.
2. Read the upgrade guide before changing code: `vendor/lettermint/lettermint-laravel/UPGRADE.md`, or https://github.com/lettermint/lettermint-laravel/blob/main/UPGRADE.md. For direct SDK calls, also read `vendor/lettermint/lettermint-php/UPGRADE.md`. Treat the guides as the source of truth and don't guess APIs; when unsure, read the classes in `vendor/lettermint/lettermint-laravel/src/` and `vendor/lettermint/lettermint-php/src/`.
3. Find every use of the package and the SDK: `Lettermint\` imports, the `Lettermint` facade, `app('lettermint')`, `app('lettermint.api')`, `EmailEndpoint`, `ApiClient`, `TeamApiTokenNotFoundException`, `->idempotencyKey(`, `->attach(`, `lettermint_webhook_payload`, `LettermintTransportFactory`, `config('lettermint.`, `LETTERMINT_` environment variables, listeners for `Lettermint\Laravel\Events\` classes, and code that reads webhook data properties (`$event->data->…`, `$event->envelope->timestamp`, `getDecodedContent()`).
4. Rewrite each use following the guide's before/after examples:
   - Replace the facade's builder chains (`Lettermint::from(...)->...->send()`) with `Lettermint::emails()->send([...])` or `Lettermint::emails()->compose()->...->send()`, assigning the result of every builder setter.
   - Replace `EmailEndpoint` and `ApiClient` type hints and container lookups with `Lettermint\Lettermint`. The parts are properties on the client (`$lettermint->emails`, `$lettermint->projects`) and methods on the facade (`Lettermint::projects()`). Use the renamed Team API methods and nested query arrays from the PHP SDK guide.
   - Move idempotency keys into `send(..., idempotencyKey: ...)`.
   - Never store the client in a property of a queued job, event or listener: it cannot be serialized. Resolve it in `handle()`.
   - Catch `Symfony\Component\Mailer\Exception\TransportException` around mail sends and read the SDK exception from `getPrevious()`; catch `Lettermint\Exceptions\*` classes around direct SDK calls.
   - Webhooks: handle the now nullable data properties listed in the guide, read the verified payload as a `Lettermint\WebhookPayload`, and make sure proxies forward the `X-Lettermint-Signature` and `X-Lettermint-Delivery` headers and the raw body.
   - Optionally rename `LETTERMINT_API_TOKEN` to `LETTERMINT_TEAM_TOKEN`; keep the old name if renaming it in every environment is not practical, because it is still read.
5. Run the project's static analysis (PHPStan/Larastan/Psalm), code style and tests, and fix every error. Don't send real email or call the live API while testing: use `Mail::fake()`, or bind a fake Guzzle client under `Lettermint\Laravel\LettermintServiceProvider::HTTP_CLIENT`.
6. Finish with a summary: the files you changed, anything you could not migrate with certainty, and behaviour changes I should review.

Never print, log or commit API tokens or webhook secrets.
````

### 1. Update Composer

```bash
composer require lettermint/lettermint-laravel:^3.0 -W
```

The package now requires `lettermint/lettermint-php` `^3.0`. If your application also requires the SDK directly, update both at once:

```bash
composer require lettermint/lettermint-laravel:^3.0 lettermint/lettermint-php:^3.0 -W
```

Code that calls the SDK directly must follow the [PHP SDK upgrade guide](https://github.com/lettermint/lettermint-php/blob/main/UPGRADE.md) as well.

### 2. Environment variables and config

The config keys are unchanged. The team token has a new environment variable name; the 2.x names keep working.

| Purpose | 2.x | 3.0 |
| --- | --- | --- |
| Project sending token (`lettermint.token`) | `LETTERMINT_PROJECT_TOKEN`, fallback `LETTERMINT_TOKEN` | Unchanged |
| Team API token (`lettermint.api_token`) | `LETTERMINT_API_TOKEN` | `LETTERMINT_TEAM_TOKEN`, fallback `LETTERMINT_API_TOKEN` |
| Timeout (`lettermint.timeout`) | `LETTERMINT_TIMEOUT`, integer seconds | `LETTERMINT_TIMEOUT`, seconds as a float (`2.5` works) |

```env
# 2.x
LETTERMINT_API_TOKEN=lm_team_...

# 3.0
LETTERMINT_TEAM_TOKEN=lm_team_...
```

If you published `config/lettermint.php`, it keeps working: it reads `LETTERMINT_API_TOKEN` as before. To read the new name too, update the `api_token` line:

```php
// 2.x
'api_token' => env('LETTERMINT_API_TOKEN'),

// 3.0
'api_token' => env('LETTERMINT_TEAM_TOKEN', env('LETTERMINT_API_TOKEN')),
```

Other behaviour changes:

- An empty or blank token counts as not configured. In 2.x an empty string in `config/lettermint.php` hid a token in `config/services.php`; now the services value is used.
- The timeout must be positive and covers the whole request, including reading the response. A timeout of `0` used to mean "no timeout"; it now throws `Lettermint\Exceptions\LettermintConfigException` when the client is built.

### 3. Sending email with Laravel mail

No changes are needed to send mail. The transport builds the same request body as 2.x.

Failed sends still throw `Symfony\Component\Mailer\Exception\TransportException` with the message `Sending email via Lettermint API failed: …`. What changes is the exception it wraps:

```php
// 2.x
try {
    Mail::to($user)->send(new WelcomeMail($user));
} catch (TransportException $e) {
    $e->getCode();     // always 0
    $e->getPrevious(); // \Exception('API request failed: …')
}

// 3.0
use Lettermint\Exceptions\RateLimitException;
use Lettermint\Exceptions\ValidationException;

try {
    Mail::to($user)->send(new WelcomeMail($user));
} catch (TransportException $e) {
    $e->getCode(); // the HTTP status, e.g. 422 or 429; 0 when no response was received
    $error = $e->getPrevious();

    if ($error instanceof ValidationException) {
        $error->errors; // ['from' => ['…']]
    } elseif ($error instanceof RateLimitException) {
        $error->retryAfter; // seconds
    }
}
```

The SDK exceptions are listed in the [PHP SDK guide](https://github.com/lettermint/lettermint-php/blob/main/UPGRADE.md#errors). Exceptions that are not Lettermint errors are no longer wrapped in a `TransportException`.

Creating the `lettermint` mailer without a project token still throws `Lettermint\Laravel\Exceptions\ApiTokenNotFoundException`.

### 4. The facade

The `Lettermint` facade resolved to the 2.x `EmailEndpoint`, a builder shared by every caller. It now resolves to the `Lettermint\Lettermint` client. Each part of the client is a facade method.

```php
use Lettermint\Laravel\Facades\Lettermint;

// 2.x
Lettermint::from('hello@example.com')
    ->to('user@example.com')
    ->subject('Hello')
    ->html('<p>Hello</p>')
    ->idempotencyKey('hello-user')
    ->send();

// 3.0: builder (every setter returns a new builder)
Lettermint::emails()->compose()
    ->from('hello@example.com')
    ->to('user@example.com')
    ->subject('Hello')
    ->html('<p>Hello</p>')
    ->send(idempotencyKey: 'hello-user');

// 3.0: array in the API's format
Lettermint::emails()->send([
    'from' => 'hello@example.com',
    'to' => ['user@example.com'],
    'subject' => 'Hello',
    'html' => '<p>Hello</p>',
], idempotencyKey: 'hello-user');
```

```php
// 2.x: send an array on the endpoint
Lettermint::idempotencyKey($key);
Lettermint::send($payload);
Lettermint::sendBatch($messages);
Lettermint::ping();

// 3.0
Lettermint::emails()->send($payload, idempotencyKey: $key);
Lettermint::emails()->sendBatch($messages);
Lettermint::emails()->ping(); // checks the project token
Lettermint::ping();           // checks the team token if configured, otherwise the project token
```

If you built an email over several statements, keep the builder each setter returns:

```php
// 2.x
Lettermint::from('hello@example.com');
Lettermint::to($user->email);
if ($copy) {
    Lettermint::cc('team@example.com');
}
Lettermint::subject('Hi')->send();

// 3.0
$email = Lettermint::emails()->compose()->from('hello@example.com')->to($user->email);
if ($copy) {
    $email = $email->cc('team@example.com');
}
$email->subject('Hi')->send();
```

Attachments, tags and the other builder methods changed as described in the [PHP SDK guide](https://github.com/lettermint/lettermint-php/blob/main/UPGRADE.md#send-an-email), for example `->attach(new Lettermint\Attachment('invoice.pdf', $bytes))` and `->tags([['name' => 'campaign', 'value' => 'autumn']])`.

### 5. The container and the Team API

| 2.x | 3.0 |
| --- | --- |
| `app(Lettermint\Endpoints\EmailEndpoint::class)` | `app(Lettermint\Lettermint::class)->emails` |
| `app('lettermint')` (an `EmailEndpoint`) | `app('lettermint')` (the `Lettermint\Lettermint` client) |
| `app(Lettermint\Client\ApiClient::class)` | `app(Lettermint\Lettermint::class)` |
| `app('lettermint.api')` | `app(Lettermint\Lettermint::class)`; the alias is removed |
| Type hint `EmailEndpoint $email` | Type hint `Lettermint\Lettermint $lettermint`, then `$lettermint->emails` |
| Type hint `ApiClient $api` | Type hint `Lettermint\Lettermint $lettermint` |
| Resolving `EmailEndpoint` without a project token threw `ApiTokenNotFoundException` | Resolving the client without any token throws `ApiTokenNotFoundException`. With only one token, calling a part that needs the other throws `Lettermint\Exceptions\LettermintConfigException` (`domains.list needs teamToken; …`) before any request |
| Resolving `ApiClient` without a team token threw `TeamApiTokenNotFoundException` | `TeamApiTokenNotFoundException` is removed; see the row above |

```php
// 2.x
use Lettermint\Client\ApiClient;

$projects = app(ApiClient::class)->projects->list(['page[size]' => 10]);
$team = app('lettermint.api')->team->retrieve();

// 3.0
use Lettermint\Laravel\Facades\Lettermint;

$projects = Lettermint::projects()->list(['page' => ['size' => 10]]);
$team = Lettermint::team()->retrieve();

// or, with an injected client
public function __construct(private \Lettermint\Lettermint $lettermint) {}

$projects = $this->lettermint->projects->list(['page' => ['size' => 10]]);
```

Some Team API methods moved, for example `$api->projects->routes($id)` is now `$lettermint->routes->list($id)` and `$api->team->members()` is `$lettermint->team->members->list()`. The [PHP SDK guide](https://github.com/lettermint/lettermint-php/blob/main/UPGRADE.md#team-api) lists every method, the query parameter format, the response type names and the exceptions.

The client is a singleton. It holds no message state, so sharing it is safe in queue workers and Octane. It refuses to be serialized because it holds your tokens: don't store it in a property of a queued job, event, listener or notification. Resolve it where you use it:

```php
class SendReport implements ShouldQueue
{
    public function handle(\Lettermint\Lettermint $lettermint): void
    {
        $lettermint->emails->send($this->message);
    }
}
```

To send the client's requests through your own Guzzle client, for example with a proxy or a test handler, bind it under `Lettermint\Laravel\LettermintServiceProvider::HTTP_CLIENT`.

### 6. Webhooks

The route, the middleware and the controller keep their names. If you register them yourself, nothing changes in your routes file.

- **The `X-Lettermint-Delivery` header is required.** Lettermint sends it with every delivery. If a proxy or load balancer sits in front of your application, make sure it forwards this header as well as `X-Lettermint-Signature`.
- **Failure reasons.** A rejected delivery still gets a `401` response; the JSON body now also has a `reason`: `signature_header_missing`, `signature_header_malformed`, `delivery_header_missing`, `delivery_timestamp_mismatch`, `timestamp_out_of_tolerance`, `signature_mismatch`, `body_invalid` or `payload_invalid`.
- **Payloads without an event name** are rejected with `401` (`payload_invalid`). In 2.x the package dispatched `UnknownWebhookEventReceived` with an empty event name; Lettermint never sends such a payload.
- **Key rotation.** A delivery is accepted when any `v1` signature in the header matches.
- **The verified payload** that `VerifyWebhookSignature` stores on the request is a `Lettermint\WebhookPayload` instead of an array. It supports array access and `toArray()`:

```php
// 2.x
$payload = $request->attributes->get('lettermint_webhook_payload'); // array
$event = $payload['event'];

// 3.0
use Lettermint\Laravel\Webhooks\VerifyWebhookSignature;

$payload = VerifyWebhookSignature::payload($request); // Lettermint\WebhookPayload
$event = $payload->event;       // $payload['event'] works too
$all = $payload->toArray();
```

- **The webhook config** is unchanged. A negative `tolerance` now throws `Lettermint\Exceptions\LettermintConfigException`.

#### Webhook data

The data objects no longer throw when Lettermint omits a field, adds one or sends a value of another type: such a field reads as `null` or an empty list, so the delivery is still acknowledged. As a result, these properties are now nullable:

| Class | Property | 2.x | 3.0 |
| --- | --- | --- | --- |
| `WebhookEnvelope` | `timestamp` | `DateTimeImmutable` | `?DateTimeImmutable` |
| `ServerResponse` | `statusCode` | `int` | `?int` |
| `MessageCreatedData` | `subject` | `string` | `?string` |
| `MessageFailedData` | `reason` | `string` | `?string` |
| `MessageSuppressedData` | `reason` | `string` | `?string` |
| `MessagePolicyRejectedData` | `subject`, `reason` | `string` | `?string` |
| `MessagePolicyRejectedData` | `score` | `float` | `?float` |
| `MessageUnsubscribedData` | `unsubscribedAt` | `DateTimeImmutable` | `?DateTimeImmutable` |
| `MessageOpenedData` | `openedAt` | `DateTimeImmutable` | `?DateTimeImmutable` |
| `MessageClickedData` | `clickedAt` | `DateTimeImmutable` | `?DateTimeImmutable` |
| `MessageClickedData` | `destinationUrl` | `string` | `?string` |
| `MessageClickedData` | `linkIndex` | `int` | `?int` |
| `MessageInboundData` | `recipient`, `subject` | `string` | `?string` |
| `MessageInboundData` | `date` | `DateTimeImmutable` | `?DateTimeImmutable` |
| `MessageInboundData` | `spamScore` | `float` (`0.0` when missing) | `?float` |
| `MessageScheduledData`, `MessageRescheduledData`, `MessageCanceledData`, `MessageReleasedData` | `scheduledAt`, `previousScheduledAt`, `canceledAt`, `releasedAt` | `string` | `?string` |
| `MessageReleasedData` | `releaseDelaySeconds` | `int` | `?int` |
| `EmailAttachment` | `content`, `contentType` | `string` | `?string` |
| `EmailAttachment` | `size` | `int` | `?int` |
| `EmailAttachment` | `getDecodedContent()` | `string` | `?string` (`null` for URL attachments or invalid base64) |
| `SpamSymbol` | `score` | `float` | `?float` |
| `WebhookTestData` | `timestamp` | `int` | `?int` |

Identifiers that every delivery carries keep their non-nullable `string` type and read as `''` if absent: `WebhookEnvelope::$id`, `messageId`, the `recipient` of sent, delivered, bounced, complaint, suppressed, unsubscribed, opened and clicked events, `MessageInboundData::$route`, the `email` of addresses, and the suppression and test-event fields.

Other changes to the data:

- Every typed event has a `payload` property with the complete verified payload, including fields the data objects do not map, such as `context`, `sandbox` and the name/value `tags`: `$event->payload['data']['tags']`.
- `EmailAttachment` has `url` and `expiresAt` for inbound routes that deliver attachments as signed URLs. In 2.x such a delivery failed with an error, because `content` was missing.
- `MessageCreatedData::$replyTo` (and `to`, `cc`, `bcc`) are lists of strings; a single `reply_to` string becomes a one-item list, and non-string items are skipped.
- The event constructors take the payload as a third, optional argument. If you construct events in tests, existing calls keep working.

### 7. Extending the transport

If you subclass `Lettermint\Laravel\Transport\LettermintTransportFactory`:

| 2.x | 3.0 |
| --- | --- |
| `__construct(EmailEndpoint $emailEndpoint, array $config = [])` | `__construct(Lettermint\Lettermint $lettermint, array $config = [])` |
| `protected EmailEndpoint $emailEndpoint` | `protected Lettermint\Lettermint $lettermint` |
| `protected getMessageId(mixed $result): ?string` | Removed; `doSend()` reads `message_id` from the `SendMailResponse` |
| Wrapped every `\Exception` in a `TransportException` | Wraps `Lettermint\Exceptions\LettermintException` only; the code is the HTTP status |

### Removed

| 2.x | 3.0 |
| --- | --- |
| `Lettermint\Endpoints\EmailEndpoint` container binding | `Lettermint\Lettermint` (`->emails`) |
| `Lettermint\Client\ApiClient` container binding | `Lettermint\Lettermint` |
| `lettermint.api` container alias | `Lettermint\Lettermint` |
| `Lettermint\Laravel\Exceptions\TeamApiTokenNotFoundException` | `Lettermint\Exceptions\LettermintConfigException` from the SDK, thrown by the Team API call |
| `Lettermint\Laravel\Lettermint` (an empty class) | Nothing; use `Lettermint\Lettermint` or the `Lettermint\Laravel\Facades\Lettermint` facade |
| `LettermintTransportFactory::getMessageId()` | Nothing |
| The facade's `EmailEndpoint` methods (`from()`, `to()`, `send()`, `idempotencyKey()`, …) | `Lettermint::emails()->compose()` and `Lettermint::emails()->send()` |


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
