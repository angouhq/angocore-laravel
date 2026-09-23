# angocore-laravel

Laravel client SDK for the [AngoCore](https://github.com/angouhq/angocore) platform — payments orchestration (Stripe / Openpay / Mercado Pago / Connect) + mail (Resend) under a single API.

## Install

Add the repo as a VCS source in your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:angouhq/angocore-laravel.git"
        }
    ],
    "require": {
        "angou/angocore-laravel": "^0.4"
    }
}
```

Then:

```bash
composer require angou/angocore-laravel
```

## Configuration

Add to `.env`:

```env
ANGOCORE_BASE_URL=https://core.angou.com.mx
ANGOCORE_API_KEY=ango_test_xxxxx...
ANGOCORE_ENV=sandbox
ANGOCORE_WEBHOOK_SECRET=whsec_xxxxx...
```

Publish the config:

```bash
php artisan vendor:publish --tag=angocore-config
```

## Usage

### Payments

```php
use Angou\Angocore\Facades\Angopay;

// Create a payment intent
$intent = Angopay::createPaymentIntent([
    'merchant_id'     => 'mer_xxx',
    'amount'          => 12500,                  // cents
    'currency'        => 'MXN',
    'description'     => 'Order #1234',
    'customer'        => ['email' => 'buyer@example.com', 'name' => 'Buyer'],
    'external_reference' => 'order_1234',
]);

// Confirm
Angopay::confirmPaymentIntent($intent['id']);

// Connect (Stripe Connect Standard)
$account = Angopay::createConnectAccount([
    'merchant_public_id' => 'mer_xxx',
    'country'            => 'MX',
    'business_email'     => 'merchant@example.com',
]);
$link = Angopay::createConnectOnboardingLink(
    $account['id'],
    return_url: 'https://app.example.com/connect/return',
    refresh_url: 'https://app.example.com/connect/refresh',
);

// Verify incoming webhook
$event = Angopay::handleIncomingWebhook($request);
```

### Mail

Configure the mailer in `config/mail.php`:

```php
'mailers' => [
    'angocore' => ['transport' => 'angocore'],
    // ...
],
```

Then create a Mailable extending `AngomailTemplate`:

```php
use Angou\Angocore\Mail\AngomailTemplate;

class OrderConfirmationMail extends AngomailTemplate
{
    public function __construct(public Order $order) {}

    protected function templateName(): string
    {
        return 'order_confirmation';
    }

    protected function templateData(): array
    {
        return [
            'customer_name' => $this->order->customer_name,
            'total'         => $this->order->total,
            'currency'      => $this->order->currency,
        ];
    }
}
```

Send:

```php
Mail::mailer('angocore')
    ->to($order->customer_email)
    ->send(new OrderConfirmationMail($order));
```

### AI

`POST /v1/ai/chat` through the `Angoai` facade. AngoCore chooses the provider
(OpenAI first, DeepSeek when OpenAI fails), meters tokens and cost per
application, and never stores prompts unless the platform enables it. The
key needs the `ai:chat` scope.

```php
use Angou\Angocore\Facades\Angoai;

$reply = Angoai::chat([
    ['role' => 'system', 'content' => 'Eres un asistente breve.'],
    ['role' => 'user', 'content' => '¿Cuánto llevo en súper?'],
], [
    'max_tokens' => 400,
    'temperature' => 0.2,
    'metadata' => ['product' => 'angogasto', 'feature' => 'assistant'],
]);

$reply['content'];   // completion text
$reply['provider'];  // 'openai' | 'deepseek'
$reply['usage'];     // ['input_tokens' => ..., 'output_tokens' => ...]

// JSON mode: decoded object under `data`, raw reply under `response`.
$structured = Angoai::json($messages)['data'];

// Just the text.
$text = Angoai::text($messages);
```

Options: `response_format` (`text` | `json`), `max_tokens`, `temperature`,
`metadata` (`product`, `feature`, `reference`) and `idempotency_key`.
`ANGOCORE_AI_TIMEOUT` (default 65 s) applies only to this endpoint.

## Idempotency

Every POST and PATCH carries an `Idempotency-Key`. AngoCore runs a request once
per key and replays the stored result to any repeat with the same key and body.

- **Pass a stable key per logical operation** when you may retry it yourself
  (a queued job, a user clicking twice): `order:1001:charge`, `refund:txn_42`,
  `cancel-sub:sub_7`. Without one, the SDK generates a new UUID per call, so
  only its own retries share a key and your retries run the operation again.
- A different body or endpoint under the same key is another operation: use a
  new key.
- When a request times out, the SDK retries it with the same key. If AngoCore
  is still running the first attempt, it answers 409 `idempotency_key_in_use`;
  the SDK then waits 0.5 s, 1 s, 2 s and every 2 s after that, and resends with
  the same key until it gets the original result or `ANGOCORE_IN_PROGRESS_WAIT`
  seconds pass (default 15, `0` disables the wait).

```php
$intent = Angopay::createPaymentIntent($payload, idempotencyKey: "order:{$order->id}:charge");
```

## Errors

- `Angou\Angocore\Exceptions\AngocoreException` — base class; every exception below extends it except `AngocoreTransportException`.
- `AngocoreAuthException` — 401/403.
- `AngocoreRateLimitException` — 429 (with `retryAfter`).
- `AngocoreIdempotencyInProgressException` — AngoCore was still running the first request with this key when the wait ran out. The operation may still succeed: retry later with the same key to get its result, never with a new key.
- `AngocoreIdempotencyKeyReusedException` — 422: the key was already used with another method, path or body.
- `AngocoreTransportException` — 5xx / connection failure (Laravel queue retries this).

## License

Proprietary — Angou. All rights reserved.
