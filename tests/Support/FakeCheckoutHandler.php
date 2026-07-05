<?php

namespace FastUcp\Tests\Support;

use FastUcp\Builders\CheckoutBuilder;
use FastUcp\Contracts\CheckoutHandler;
use FastUcp\Contracts\SessionStore;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Order;
use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;
use Illuminate\Support\Str;

/**
 * Reference merchant implementation used by the test suite. Mirrors the
 * Python example server: a tiny catalog, shipping appears once buyer
 * email is present, sessions persist via the SessionStore contract.
 */
class FakeCheckoutHandler implements CheckoutHandler
{
    public const PRODUCTS = [
        'sku_pixel' => ['title' => 'Pixel 9 Pro', 'price' => 99900, 'image' => 'https://img.test/pixel.png'],
        'sku_watch' => ['title' => 'Pixel Watch 3', 'price' => 39900, 'image' => 'https://img.test/watch.png'],
    ];

    public function __construct(
        protected UcpManager $manager,
        protected SessionStore $store,
    ) {}

    public function createCheckout(array $payload): CheckoutResponse
    {
        $builder = new CheckoutBuilder($this->manager, 'chk_'.Str::random(12), $payload['currency'] ?? null);

        $builder->addLink('privacy_policy', 'https://merchant.test/privacy');
        $builder->addLink('terms_of_service', 'https://merchant.test/terms');

        foreach ($payload['line_items'] as $lineItem) {
            $sku = $lineItem['item']['id'];
            $product = self::PRODUCTS[$sku] ?? null;

            if ($product === null) {
                throw new UcpException('out_of_stock', "Unknown product: {$sku}", '$.line_items');
            }

            $builder->addItem($sku, $product['title'], $product['price'], $lineItem['quantity'], $product['image']);
        }

        $builder->setBuyer($payload['buyer'] ?? null);

        $checkout = $builder->build();
        $this->store->save($checkout->id, $checkout->toArray());

        return $checkout;
    }

    public function updateCheckout(string $id, array $payload): CheckoutResponse
    {
        $existing = $this->store->get($id)
            ?? throw new UcpException('not_found', "Session not found: {$id}", null, 'recoverable', 404);

        $builder = new CheckoutBuilder($this->manager, $id, $existing['currency'] ?? null);

        $builder->addLink('privacy_policy', 'https://merchant.test/privacy');
        $builder->addLink('terms_of_service', 'https://merchant.test/terms');

        foreach ($existing['line_items'] as $lineItem) {
            $builder->addItem(
                $lineItem['item']['id'],
                $lineItem['item']['title'],
                $lineItem['item']['price'],
                $lineItem['quantity'],
                $lineItem['item']['image_url'] ?? null,
            );
        }

        $buyer = $payload['buyer'] ?? $existing['buyer'] ?? null;
        $builder->setBuyer($buyer);

        if (! empty($buyer['email'])) {
            $builder->addShippingOption('ship_std', 'Standard Shipping', 500, '5-7 days');
            $builder->addShippingOption('ship_exp', 'Express Shipping', 1500, '1-2 days');
            $builder->selectShippingOption('ship_std');
        }

        $checkout = $builder->build();
        $this->store->save($id, $checkout->toArray());

        return $checkout;
    }

    public function completeCheckout(string $id, array $payment): Order
    {
        $existing = $this->store->get($id)
            ?? throw new UcpException('not_found', "Session not found: {$id}", null, 'recoverable', 404);

        if (empty($payment['token'])) {
            throw new UcpException('payment_declined', 'A payment token is required.', '$.payment');
        }

        $orderId = 'ord_'.Str::random(8);

        $lineItems = array_map(fn ($li) => [
            'id' => $li['id'],
            'item' => $li['item'],
            'quantity' => ['total' => $li['quantity'], 'fulfilled' => 0],
            'totals' => $li['totals'],
            'status' => 'processing',
        ], $existing['line_items']);

        $order = Order::fromArray([
            'ucp' => $this->manager->createUcpContext(),
            'id' => $orderId,
            'checkout_id' => $id,
            'permalink_url' => "https://merchant.test/orders/{$orderId}",
            'line_items' => $lineItems,
            'fulfillment' => ['expectations' => [], 'events' => []],
            'totals' => $existing['totals'],
        ]);

        $this->store->delete($id);

        return $order;
    }
}
