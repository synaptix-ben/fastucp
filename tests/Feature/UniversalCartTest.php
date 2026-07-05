<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Events\UniversalCartUpdated;
use FastUcp\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class UniversalCartTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Agents identify their cart via header; browser sessions don't
        // persist across requests in the test client.
        $this->withHeader('X-UCP-Cart-Id', 'test-cart-1');
    }

    public function test_add_items_from_multiple_merchants_groups_them(): void
    {
        Event::fake([UniversalCartUpdated::class]);

        $this->postJson('/ucp/cart/items', [
            'item_id' => 'sku_pixel',
            'title' => 'Pixel 9 Pro',
            'price' => 99900,
            'quantity' => 1,
        ])->assertCreated();

        $this->postJson('/ucp/cart/items', [
            'merchant_url' => 'https://other-store.test',
            'merchant_name' => 'Other Store',
            'item_id' => 'sku_shoes',
            'title' => 'Running Shoes',
            'price' => 12900,
            'quantity' => 2,
        ])->assertCreated();

        $cart = $this->getJson('/ucp/cart')->assertOk()->json();

        $this->assertCount(2, $cart['merchants']);
        $this->assertFalse($cart['single_merchant']);
        $this->assertSame(3, $cart['item_count']);
        $this->assertSame(99900 + 2 * 12900, $cart['total']);

        Event::assertDispatched(UniversalCartUpdated::class, 2);
    }

    public function test_adding_same_item_twice_increments_quantity(): void
    {
        $payload = ['item_id' => 'sku_pixel', 'title' => 'Pixel 9 Pro', 'price' => 99900];

        $this->postJson('/ucp/cart/items', $payload);
        $cart = $this->postJson('/ucp/cart/items', $payload)->json();

        $this->assertSame(2, $cart['merchants'][0]['items'][0]['quantity']);
        $this->assertCount(1, $cart['merchants'][0]['items']);
    }

    public function test_update_and_remove_items(): void
    {
        $cart = $this->postJson('/ucp/cart/items', [
            'item_id' => 'sku_watch', 'title' => 'Pixel Watch 3', 'price' => 39900,
        ])->json();

        $itemId = $cart['merchants'][0]['items'][0]['id'];

        $updated = $this->patchJson("/ucp/cart/items/{$itemId}", ['quantity' => 5])->assertOk()->json();
        $this->assertSame(5, $updated['merchants'][0]['items'][0]['quantity']);

        $emptied = $this->deleteJson("/ucp/cart/items/{$itemId}")->assertOk()->json();
        $this->assertSame([], $emptied['merchants']);
    }

    public function test_single_merchant_checkout_uses_local_handler(): void
    {
        // Local items (no merchant_url) check out against this app itself.
        Http::fake([
            'merchant.test/*' => Http::response($this->localCheckoutResponse(), 201),
        ]);

        $this->postJson('/ucp/cart/items', [
            'item_id' => 'sku_pixel', 'title' => 'Pixel 9 Pro', 'price' => 99900,
        ]);

        $result = $this->postJson('/ucp/cart/checkout', [
            'buyer' => ['email' => 'buyer@example.com'],
        ])->assertCreated()->json();

        $this->assertTrue($result['single_merchant']);
        $this->assertCount(1, $result['sessions']);
        $this->assertNull($result['sessions'][0]['merchant_url']);
        $this->assertArrayHasKey('checkout_id', $result['sessions'][0]);
    }

    public function test_multi_merchant_checkout_fans_out(): void
    {
        Http::fake([
            'merchant.test/*' => Http::response($this->localCheckoutResponse(), 201),
            'store-a.test/*' => Http::response($this->remoteCheckoutResponse('chk_a', 'https://store-a.test/continue'), 201),
            'store-b.test/*' => Http::response($this->remoteCheckoutResponse('chk_b', null), 201),
        ]);

        $this->postJson('/ucp/cart/items', [
            'item_id' => 'sku_local', 'title' => 'Local Item', 'price' => 1000,
        ]);
        $this->postJson('/ucp/cart/items', [
            'merchant_url' => 'https://store-a.test',
            'item_id' => 'sku_a', 'title' => 'Item A', 'price' => 2000,
        ]);
        $this->postJson('/ucp/cart/items', [
            'merchant_url' => 'https://store-b.test',
            'item_id' => 'sku_b', 'title' => 'Item B', 'price' => 3000,
        ]);

        $result = $this->postJson('/ucp/cart/checkout')->assertCreated()->json();

        $this->assertFalse($result['single_merchant']);
        $this->assertCount(3, $result['sessions']);

        $byMerchant = collect($result['sessions'])->keyBy('merchant_url');
        $this->assertSame('chk_a', $byMerchant['https://store-a.test']['checkout_id']);
        $this->assertSame('https://store-a.test/continue', $byMerchant['https://store-a.test']['continue_url']);
        $this->assertSame('chk_b', $byMerchant['https://store-b.test']['checkout_id']);
    }

    public function test_failed_merchant_reports_error_without_breaking_others(): void
    {
        Http::fake([
            'down-store.test/*' => Http::response(['messages' => [['code' => 'oops', 'content' => 'down']]], 500),
        ]);

        $this->postJson('/ucp/cart/items', [
            'merchant_url' => 'https://down-store.test',
            'item_id' => 'sku_x', 'title' => 'X', 'price' => 100,
        ]);

        $result = $this->postJson('/ucp/cart/checkout')->assertCreated()->json();

        $this->assertArrayHasKey('error', $result['sessions'][0]);
    }

    public function test_empty_cart_cannot_checkout(): void
    {
        $this->postJson('/ucp/cart/checkout')->assertStatus(422);
    }

    public function test_cart_view_renders(): void
    {
        $this->postJson('/ucp/cart/items', [
            'item_id' => 'sku_pixel', 'title' => 'Pixel 9 Pro', 'price' => 99900,
        ]);

        $this->get('/ucp/cart/view')
            ->assertOk()
            ->assertSee('Pixel 9 Pro')
            ->assertSee('Check out');
    }

    protected function localCheckoutResponse(): array
    {
        return $this->remoteCheckoutResponse('chk_local', null);
    }

    protected function remoteCheckoutResponse(string $id, ?string $continueUrl): array
    {
        $response = [
            'ucp' => ['version' => '2026-01-11', 'capabilities' => []],
            'id' => $id,
            'line_items' => [],
            'status' => $continueUrl !== null ? 'requires_escalation' : 'ready_for_complete',
            'currency' => 'USD',
            'totals' => [['type' => 'total', 'amount' => 1000]],
            'links' => [],
            'payment' => ['handlers' => []],
        ];

        if ($continueUrl !== null) {
            $response['continue_url'] = $continueUrl;
        }

        return $response;
    }
}
