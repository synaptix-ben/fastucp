# Quickstart: a UCP merchant in 5 minutes

This walkthrough builds a minimal merchant with product discovery and a full checkout flow.

## 1. Discovery handler

```php
// app/Ucp/CatalogHandler.php
namespace App\Ucp;

use FastUcp\Contracts\DiscoveryHandler;

class CatalogHandler implements DiscoveryHandler
{
    public const PRODUCTS = [
        'sku_tee' => ['title' => 'Logo T-Shirt', 'price' => 2500, 'image' => 'https://cdn.example/tee.png'],
        'sku_mug' => ['title' => 'Coffee Mug', 'price' => 1200, 'image' => 'https://cdn.example/mug.png'],
    ];

    public function search(string $query): array
    {
        $items = [];
        foreach (self::PRODUCTS as $sku => $p) {
            if ($query === '' || stripos($p['title'], $query) !== false) {
                $items[] = ['id' => $sku, 'title' => $p['title'], 'price' => $p['price'], 'image_url' => $p['image']];
            }
        }

        return ['items' => $items];
    }
}
```

## 2. Checkout handler

```php
// app/Ucp/ShopCheckoutHandler.php
namespace App\Ucp;

use FastUcp\Builders\CheckoutBuilder;
use FastUcp\Contracts\CheckoutHandler;
use FastUcp\Contracts\SessionStore;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Order;
use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;
use Illuminate\Support\Str;

class ShopCheckoutHandler implements CheckoutHandler
{
    public function __construct(
        private UcpManager $manager,
        private SessionStore $store,
    ) {}

    public function createCheckout(array $payload): CheckoutResponse
    {
        $builder = new CheckoutBuilder($this->manager, 'chk_'.Str::random(12));
        $builder->addLink('privacy_policy', url('/privacy'));
        $builder->addLink('terms_of_service', url('/terms'));

        foreach ($payload['line_items'] as $li) {
            $product = CatalogHandler::PRODUCTS[$li['item']['id']]
                ?? throw new UcpException('out_of_stock', 'Unknown product', '$.line_items');

            $builder->addItem($li['item']['id'], $product['title'], $product['price'], $li['quantity'], $product['image']);
        }

        $checkout = $builder->setBuyer($payload['buyer'] ?? null)->build();
        $this->store->save($checkout->id, $checkout->toArray());

        return $checkout;
    }

    public function updateCheckout(string $id, array $payload): CheckoutResponse
    {
        $existing = $this->store->get($id)
            ?? throw new UcpException('not_found', 'Session not found', null, 'recoverable', 404);

        $builder = new CheckoutBuilder($this->manager, $id, $existing['currency']);
        $builder->addLink('privacy_policy', url('/privacy'));
        $builder->addLink('terms_of_service', url('/terms'));

        foreach ($existing['line_items'] as $li) {
            $builder->addItem($li['item']['id'], $li['item']['title'], $li['item']['price'], $li['quantity'], $li['item']['image_url'] ?? null);
        }

        $buyer = $payload['buyer'] ?? $existing['buyer'] ?? null;
        $builder->setBuyer($buyer);

        if (! empty($buyer['email'])) {
            $builder->addShippingOption('std', 'Standard Shipping', 500, '5-7 days');
            $builder->selectShippingOption('std');
        }

        $checkout = $builder->build();
        $this->store->save($id, $checkout->toArray());

        return $checkout;
    }

    public function completeCheckout(string $id, array $payment): Order
    {
        $existing = $this->store->get($id)
            ?? throw new UcpException('not_found', 'Session not found', null, 'recoverable', 404);

        // Verify $payment['token'] with your PSP here.

        $orderId = 'ord_'.Str::random(8);

        $order = Order::fromArray([
            'ucp' => $this->manager->createUcpContext(),
            'id' => $orderId,
            'checkout_id' => $id,
            'permalink_url' => url("/orders/{$orderId}"),
            'line_items' => array_map(fn ($li) => [
                'id' => $li['id'],
                'item' => $li['item'],
                'quantity' => ['total' => $li['quantity'], 'fulfilled' => 0],
                'totals' => $li['totals'],
                'status' => 'processing',
            ], $existing['line_items']),
            'fulfillment' => ['expectations' => [], 'events' => []],
            'totals' => $existing['totals'],
        ]);

        $this->store->delete($id);

        return $order;
    }
}
```

## 3. Wire it up

```php
// config/ucp.php
'handlers' => [
    'checkout' => App\Ucp\ShopCheckoutHandler::class,
    'discovery' => App\Ucp\CatalogHandler::class,
],
'protocols' => ['mcp' => true],
```

## 4. Try it

```bash
# Discovery manifest
curl -s http://localhost:8000/.well-known/ucp | jq

# Create a checkout
curl -s -X POST http://localhost:8000/ucp/checkout-sessions \
  -H 'Content-Type: application/json' \
  -d '{"line_items":[{"item":{"id":"sku_tee"},"quantity":2}]}' | jq

# As an MCP agent
curl -s -X POST http://localhost:8000/ucp/mcp \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | jq
```

Next: [Checkout Builder](checkout-builder.md) · [Protocols](protocols.md) · [Universal Cart](universal-cart.md)
