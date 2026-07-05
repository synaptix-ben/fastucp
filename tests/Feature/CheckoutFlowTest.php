<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Events\CheckoutCompleted;
use FastUcp\Events\CheckoutCreated;
use FastUcp\Events\CheckoutUpdated;
use FastUcp\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class CheckoutFlowTest extends TestCase
{
    public function test_full_checkout_flow_create_update_complete(): void
    {
        Event::fake([CheckoutCreated::class, CheckoutUpdated::class, CheckoutCompleted::class]);

        // 1. Create
        $create = $this->postJson('/ucp/checkout-sessions', [
            'line_items' => [
                ['item' => ['id' => 'sku_pixel'], 'quantity' => 1],
            ],
            'currency' => 'USD',
        ]);

        $create->assertCreated()
            ->assertJsonPath('status', 'ready_for_complete')
            ->assertJsonPath('currency', 'USD')
            ->assertJsonPath('line_items.0.item.id', 'sku_pixel')
            ->assertJsonPath('totals.0.type', 'subtotal')
            ->assertJsonPath('totals.0.amount', 99900);

        $sessionId = $create->json('id');
        Event::assertDispatched(CheckoutCreated::class);

        // 2. Update with buyer -> shipping options appear
        $update = $this->patchJson("/ucp/checkout-sessions/{$sessionId}", [
            'buyer' => ['email' => 'buyer@example.com', 'first_name' => 'Ada'],
        ]);

        $update->assertOk()
            ->assertJsonPath('buyer.email', 'buyer@example.com')
            ->assertJsonPath('fulfillment.methods.0.groups.0.selected_option_id', 'ship_std');

        // Subtotal + shipping = total
        $totals = collect($update->json('totals'))->keyBy('type');
        $this->assertSame(99900, $totals['subtotal']['amount']);
        $this->assertSame(500, $totals['fulfillment']['amount']);
        $this->assertSame(100400, $totals['total']['amount']);
        Event::assertDispatched(CheckoutUpdated::class);

        // 3. Complete
        $complete = $this->postJson("/ucp/checkout-sessions/{$sessionId}/complete", [
            'payment' => ['token' => 'tok_visa_test', 'type' => 'tokenized_card'],
        ]);

        $complete->assertCreated()
            ->assertJsonPath('checkout_id', $sessionId)
            ->assertJsonPath('line_items.0.status', 'processing')
            ->assertJsonPath('line_items.0.quantity.total', 1);

        Event::assertDispatched(CheckoutCompleted::class);
    }

    public function test_create_validates_line_items(): void
    {
        $this->postJson('/ucp/checkout-sessions', ['line_items' => []])
            ->assertUnprocessable();
    }

    public function test_unknown_product_returns_ucp_error_shape(): void
    {
        $response = $this->postJson('/ucp/checkout-sessions', [
            'line_items' => [['item' => ['id' => 'sku_nonexistent'], 'quantity' => 1]],
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('messages.0.type', 'error')
            ->assertJsonPath('messages.0.code', 'out_of_stock');
    }

    public function test_buyer_without_email_yields_incomplete_status(): void
    {
        $response = $this->postJson('/ucp/checkout-sessions', [
            'line_items' => [['item' => ['id' => 'sku_pixel'], 'quantity' => 1]],
            'buyer' => ['first_name' => 'NoEmail'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'incomplete')
            ->assertJsonPath('messages.0.code', 'missing')
            ->assertJsonPath('messages.0.path', '$.buyer.email');
    }

    public function test_complete_without_token_is_declined(): void
    {
        $create = $this->postJson('/ucp/checkout-sessions', [
            'line_items' => [['item' => ['id' => 'sku_pixel'], 'quantity' => 1]],
        ]);

        $this->postJson("/ucp/checkout-sessions/{$create->json('id')}/complete", [
            'payment' => ['type' => 'card'],
        ])->assertStatus(400)
            ->assertJsonPath('messages.0.code', 'payment_declined');
    }

    public function test_completing_unknown_session_returns_404(): void
    {
        $this->postJson('/ucp/checkout-sessions/chk_missing/complete', [
            'payment' => ['token' => 'tok'],
        ])->assertStatus(404)
            ->assertJsonPath('messages.0.code', 'not_found');
    }
}
