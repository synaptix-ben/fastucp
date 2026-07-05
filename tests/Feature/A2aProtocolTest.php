<?php

namespace FastUcp\Tests\Feature;

use FastUcp\Tests\TestCase;

class A2aProtocolTest extends TestCase
{
    public function test_agent_card_lists_capabilities(): void
    {
        $response = $this->getJson('/.well-known/agent-card.json');

        $response->assertOk()->assertJsonPath('type', 'agent-card');

        $capabilities = array_column(
            $response->json('extensions.0.params.capabilities'),
            'name'
        );
        $this->assertContains('dev.ucp.shopping.checkout', $capabilities);
    }

    public function test_data_part_creates_checkout(): void
    {
        $response = $this->postJson('/ucp/agent/message', [
            'jsonrpc' => '2.0',
            'id' => 'msg-1',
            'params' => [
                'message' => [
                    'role' => 'user',
                    'kind' => 'message',
                    'messageId' => 'm1',
                    'parts' => [
                        [
                            'kind' => 'data',
                            'data' => [
                                'action' => 'add_to_checkout',
                                'line_items' => [['item' => ['id' => 'sku_pixel'], 'quantity' => 1]],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result.role', 'agent')
            ->assertJsonPath('result.parts.0.kind', 'data');

        $checkout = $response->json('result.parts.0.data')['a2a.ucp.checkout'] ?? null;
        $this->assertNotNull($checkout);
        $this->assertSame('sku_pixel', $checkout['line_items'][0]['item']['id']);
    }

    public function test_text_part_maps_to_catalog_search(): void
    {
        $response = $this->postJson('/ucp/agent/message', [
            'jsonrpc' => '2.0',
            'id' => 'msg-2',
            'message' => [
                'role' => 'user',
                'kind' => 'message',
                'messageId' => 'm2',
                'parts' => [
                    ['kind' => 'text', 'text' => 'Watch'],
                ],
            ],
        ]);

        $response->assertOk();
        $data = $response->json('result.parts.0.data');
        $this->assertSame('sku_watch', $data['items'][0]['id']);
    }

    public function test_unknown_action_returns_error(): void
    {
        $response = $this->postJson('/ucp/agent/message', [
            'jsonrpc' => '2.0',
            'id' => 'msg-3',
            'message' => [
                'parts' => [
                    ['kind' => 'data', 'data' => ['action' => 'self_destruct']],
                ],
            ],
        ]);

        $response->assertStatus(500)->assertJsonPath('error.code', -32603);
    }

    public function test_message_without_parts_returns_error(): void
    {
        $this->postJson('/ucp/agent/message', [
            'jsonrpc' => '2.0',
            'id' => 'msg-4',
            'message' => ['parts' => []],
        ])->assertStatus(500);
    }
}
