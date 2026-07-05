<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Tests\TestCase;

class DiscoveryTest extends TestCase
{
    public function test_manifest_is_served_at_well_known_ucp(): void
    {
        $response = $this->getJson('/.well-known/ucp');

        $response->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertJsonPath('ucp.version', '2026-01-11');

        $shopping = $response->json('ucp.services')['dev.ucp.shopping'];
        $this->assertSame('http://merchant.test', $shopping['rest']['endpoint']);
    }

    public function test_manifest_advertises_capabilities_from_registered_handlers(): void
    {
        $response = $this->getJson('/.well-known/ucp');

        $names = array_column($response->json('ucp.capabilities'), 'name');

        $this->assertContains('dev.ucp.shopping.checkout', $names);
        $this->assertContains('dev.ucp.shopping.order', $names);
        $this->assertContains('dev.ucp.shopping.discovery', $names);
    }

    public function test_manifest_advertises_enabled_transports(): void
    {
        $response = $this->getJson('/.well-known/ucp');

        $shopping = $response->json('ucp.services')['dev.ucp.shopping'];

        $this->assertSame('http://merchant.test/ucp/mcp', $shopping['mcp']['endpoint']);
        $this->assertSame('http://merchant.test/.well-known/agent-card.json', $shopping['a2a']['endpoint']);
        $this->assertArrayHasKey('embedded', $shopping);
    }

    public function test_manifest_includes_payment_handlers_when_registered(): void
    {
        $manager = $this->app->make(\FastUcp\UcpManager::class);
        $manager->registerPaymentHandler(\FastUcp\Presets\GooglePay::make(
            merchantName: 'Test Merchant',
            merchantId: 'm_123',
            gateway: 'stripe',
            gatewayMerchantId: 'acct_1',
        ));

        $response = $this->getJson('/.well-known/ucp');

        $response->assertJsonPath('payment.handlers.0.name', 'com.google.pay');
    }
}
