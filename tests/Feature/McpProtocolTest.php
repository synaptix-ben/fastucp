<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Tests\TestCase;

class McpProtocolTest extends TestCase
{
    protected function rpc(string $method, array $params = [], int $id = 1): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/ucp/mcp', [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ]);
    }

    public function test_initialize_handshake(): void
    {
        $this->rpc('initialize')
            ->assertOk()
            ->assertJsonPath('result.protocolVersion', '2024-11-05')
            ->assertJsonPath('result.serverInfo.name', 'Test Merchant');
    }

    public function test_tools_list_exposes_registered_handlers(): void
    {
        $response = $this->rpc('tools/list');

        $names = array_column($response->json('result.tools'), 'name');

        $this->assertSame(
            ['search_products', 'create_checkout', 'update_checkout', 'complete_checkout'],
            $names
        );
    }

    public function test_tools_call_search_products(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'search_products',
            'arguments' => ['query' => 'Pixel 9'],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.structuredContent.items.0.id', 'sku_pixel');
    }

    public function test_tools_call_full_checkout_via_mcp(): void
    {
        $create = $this->rpc('tools/call', [
            'name' => 'create_checkout',
            'arguments' => [
                'line_items' => [['item' => ['id' => 'sku_watch'], 'quantity' => 2]],
            ],
        ]);

        $sessionId = $create->json('result.structuredContent.id');
        $this->assertNotNull($sessionId);
        $this->assertSame(79800, $create->json('result.structuredContent.totals.0.amount'));

        $complete = $this->rpc('tools/call', [
            'name' => 'complete_checkout',
            'arguments' => ['id' => $sessionId, 'payment' => ['token' => 'tok_test']],
        ], 2);

        $complete->assertJsonPath('result.structuredContent.checkout_id', $sessionId);
    }

    public function test_unknown_tool_returns_method_not_found(): void
    {
        $this->rpc('tools/call', ['name' => 'nuke_database', 'arguments' => []])
            ->assertJsonPath('error.code', -32601);
    }

    public function test_unknown_method_returns_error(): void
    {
        $this->rpc('resources/list')->assertJsonPath('error.code', -32601);
    }

    public function test_tool_level_error_uses_is_error_flag(): void
    {
        $response = $this->rpc('tools/call', [
            'name' => 'complete_checkout',
            'arguments' => ['id' => 'chk_missing', 'payment' => ['token' => 't']],
        ]);

        $response->assertOk()->assertJsonPath('result.isError', true);
    }

    public function test_get_returns_status(): void
    {
        $this->getJson('/ucp/mcp')->assertOk()->assertJsonPath('status', 'online');
    }
}
