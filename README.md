# FastUCP for Laravel ⚡️

**Build Universal Commerce Protocol (UCP) merchant servers and commerce agents in Laravel.**

FastUCP is a Laravel composer package implementing the [Universal Commerce Protocol](https://ucp.dev) — the open standard (backed by Google, Shopify, and major retailers) that lets AI agents discover products, build carts, and complete checkout against any merchant. It is a full port of the Python [fastucp](https://github.com/MehmetHilmiEmel/fastucp) framework, extended with **Embedded Checkout** and a multi-merchant **Universal Cart**.

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-10%20%7C%2011%20%7C%2012-red)](https://laravel.com/)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

## Features

- ⚡ **Auto-discovery** — `/.well-known/ucp` manifest generated from your registered handlers
- 🛒 **Checkout lifecycle** — create / update / complete sessions over REST with Laravel validation
- 🤖 **MCP server** — expose your store as JSON-RPC 2.0 tools for LLM agents
- 🤝 **A2A protocol** — agent card + structured and natural-language message handling
- 🖼️ **Embedded Checkout (ECP)** — render your checkout inside an agent's iframe with JSON-RPC 2.0 over postMessage: delegation negotiation (`ec_delegate`), origin pinning, MessagePort upgrade — optionally powered by Shopify's `<shopify-checkout>` component
- 🛍️ **Universal Cart** — one persistent cart across any number of UCP merchants with per-merchant fan-out checkout; works equally well for single-merchant stores
- 💳 **Payment presets** — Google Pay and Stripe handlers out of the box
- 🔏 **JWS response signing** — ES256 `UCP-Signature` headers, public keys advertised in the manifest
- 📢 **Lifecycle events** — `CheckoutCreated`, `CheckoutUpdated`, `CheckoutCompleted`, `UniversalCartUpdated`, and more
- 🧱 **Builder pattern** — fluent `CheckoutBuilder` with auto-calculated totals and fulfillment hierarchy

## Installation

Until the package is published on Packagist, install it straight from this repository — add to your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/synaptix-ben/fastucp" }
]
```

then:

```bash
composer require fastucp/laravel:dev-main

php artisan vendor:publish --tag=ucp-config
php artisan migrate   # checkout sessions, orders, universal cart tables
```

(Once published to Packagist, the repositories entry becomes unnecessary.)

## Quick start

**1. Implement the checkout contract:**

```php
namespace App\Ucp;

use FastUcp\Builders\CheckoutBuilder;
use FastUcp\Contracts\CheckoutHandler;
use FastUcp\Contracts\SessionStore;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Order;
use FastUcp\UcpManager;

class StoreCheckoutHandler implements CheckoutHandler
{
    public function __construct(
        private UcpManager $manager,
        private SessionStore $store,
    ) {}

    public function createCheckout(array $payload): CheckoutResponse
    {
        $builder = new CheckoutBuilder($this->manager, 'chk_'.str()->random(12));
        $builder->addLink('privacy_policy', url('/privacy'));
        $builder->addLink('terms_of_service', url('/terms'));

        foreach ($payload['line_items'] as $li) {
            $product = Product::findOrFail($li['item']['id']);
            $builder->addItem(
                $product->sku, $product->title,
                $product->price_cents, $li['quantity'], $product->image_url,
            );
        }

        $checkout = $builder->setBuyer($payload['buyer'] ?? null)->build();
        $this->store->save($checkout->id, $checkout->toArray());

        return $checkout;
    }

    public function updateCheckout(string $id, array $payload): CheckoutResponse { /* ... */ }

    public function completeCheckout(string $id, array $payment): Order { /* ... */ }
}
```

**2. Register it in `config/ucp.php`:**

```php
'base_url' => env('UCP_BASE_URL', 'https://store.example.com'),

'handlers' => [
    'checkout' => App\Ucp\StoreCheckoutHandler::class,
    'discovery' => App\Ucp\StoreDiscoveryHandler::class,
],

'protocols' => [
    'mcp' => true,       // JSON-RPC tools for LLM agents
    'a2a' => true,       // agent-to-agent messaging
    'embedded' => true,  // iframe checkout via ECP
],

'universal_cart' => ['enabled' => true],
```

**3. Done.** Your store now serves:

| Endpoint | Purpose |
|---|---|
| `GET /.well-known/ucp` | Discovery manifest |
| `POST /ucp/checkout-sessions` | Create checkout |
| `PATCH /ucp/checkout-sessions/{id}` | Update checkout (buyer, shipping) |
| `POST /ucp/checkout-sessions/{id}/complete` | Complete checkout → Order |
| `GET/POST /ucp/mcp` | MCP JSON-RPC 2.0 server |
| `GET /.well-known/agent-card.json` | A2A agent card |
| `POST /ucp/agent/message` | A2A message handler |
| `GET /ucp/embedded-checkout/{id}` | Embedded checkout UI (iframe) |
| `GET/POST/PATCH/DELETE /ucp/cart/...` | Universal cart API |

## Consuming other merchants (client)

```php
use FastUcp\Client\UcpClient;

$client = new UcpClient('https://other-store.example.com', transport: 'mcp');
$client->discover();

$results = $client->searchProducts('running shoes');

$checkout = $client->createCheckout([
    ['item' => ['id' => $results['items'][0]['id']], 'quantity' => 1],
]);
$checkout = $client->updateCheckout($checkout->id, ['buyer' => ['email' => 'a@b.com']]);
$order = $client->completeCheckout($checkout->id, ['token' => 'tok_...', 'type' => 'tokenized_card']);
```

## Universal Cart

Add items from any UCP merchant into one cart; checkout fans out one session per merchant:

```http
POST /ucp/cart/items
X-UCP-Cart-Id: my-agent-cart-42

{ "merchant_url": "https://store-a.test", "item_id": "sku_1",
  "title": "Shoes", "price": 12900, "quantity": 1 }
```

```http
POST /ucp/cart/checkout
X-UCP-Cart-Id: my-agent-cart-42

→ { "single_merchant": false,
    "sessions": [
      { "merchant_url": "https://store-a.test", "checkout_id": "chk_a",
        "continue_url": "https://store-a.test/ucp/embedded-checkout/chk_a", ... },
      ...
    ] }
```

Headless agents identify their cart with the `X-UCP-Cart-Id` header; browser users fall back to their auth user or session automatically.

## Listening to checkout events

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \FastUcp\Events\CheckoutCompleted::class => [
        \App\Listeners\SendOrderConfirmation::class,
        \App\Listeners\DecrementInventory::class,
    ],
];
```

## Documentation

- [Installation](docs/installation.md)
- [Quickstart](docs/quickstart.md)
- [Checkout Builder](docs/checkout-builder.md)
- [Protocols: MCP, A2A, Embedded Checkout](docs/protocols.md)
- [Universal Cart](docs/universal-cart.md)
- [Payment Handlers](docs/payment-handlers.md)
- [Events](docs/events.md)
- [Security & Signing](docs/security.md)

## Testing

```bash
composer install
vendor/bin/phpunit
```

## License

MIT. UCP schemas are © UCP Authors, Apache 2.0.
