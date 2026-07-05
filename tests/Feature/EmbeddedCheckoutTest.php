<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Events\EmbeddedCheckoutReady;
use FastUcp\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class EmbeddedCheckoutTest extends TestCase
{
    protected function createSession(): string
    {
        return $this->postJson('/ucp/checkout-sessions', [
            'line_items' => [['item' => ['id' => 'sku_pixel'], 'quantity' => 1]],
        ])->json('id');
    }

    public function test_embedded_checkout_page_renders_session(): void
    {
        $sessionId = $this->createSession();

        $response = $this->get("/ucp/embedded-checkout/{$sessionId}");

        $response->assertOk()
            ->assertSee('Pixel 9 Pro')
            ->assertSee('UcpEcpBridge', false)
            ->assertSee($sessionId, false);
    }

    public function test_delegates_are_negotiated_from_query(): void
    {
        Event::fake([EmbeddedCheckoutReady::class]);
        $sessionId = $this->createSession();

        $this->get("/ucp/embedded-checkout/{$sessionId}?ec_delegate=payment.credential,fulfillment.address_change,unsupported.thing")
            ->assertOk();

        Event::assertDispatched(EmbeddedCheckoutReady::class, function ($event) {
            return $event->session->acceptedDelegates === ['payment.credential', 'fulfillment.address_change']
                && in_array('unsupported.thing', $event->session->requestedDelegates, true);
        });
    }

    public function test_unknown_session_returns_404(): void
    {
        $this->get('/ucp/embedded-checkout/chk_missing')->assertStatus(404);
    }

    public function test_origin_whitelist_blocks_unlisted_hosts(): void
    {
        config()->set('ucp.embedded.allowed_origins', ['https://agent.example']);
        $sessionId = $this->createSession();

        $this->get("/ucp/embedded-checkout/{$sessionId}?ec_origin=https://evil.example")
            ->assertStatus(403);

        $this->get("/ucp/embedded-checkout/{$sessionId}?ec_origin=https://agent.example")
            ->assertOk();
    }

    public function test_builder_escalates_with_continue_url(): void
    {
        $manager = $this->app->make(\FastUcp\UcpManager::class);

        $checkout = (new \FastUcp\Builders\CheckoutBuilder($manager, 'chk_ecp'))
            ->addItem('sku_pixel', 'Pixel 9 Pro', 99900, 1)
            ->setContinueUrl('http://merchant.test/ucp/embedded-checkout/chk_ecp')
            ->build();

        $this->assertSame('requires_escalation', $checkout->status);
        $this->assertSame('http://merchant.test/ucp/embedded-checkout/chk_ecp', $checkout->continueUrl);
    }
}
