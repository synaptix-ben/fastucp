<?php

namespace FastUcp\Protocols;

use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;
use Illuminate\Support\Str;
use Throwable;

/**
 * Agent-to-Agent (A2A) message protocol, per the UCP A2A binding.
 */
class A2aProtocol
{
    /**
     * Maps A2A actions to the internal handler registry.
     */
    protected const ACTION_MAP = [
        'add_to_checkout' => 'create_checkout',
        'create_checkout' => 'create_checkout',
        'update_checkout' => 'update_checkout',
        'complete_checkout' => 'complete_checkout',
        'search_shopping_catalog' => 'search_products',
        'search_products' => 'search_products',
    ];

    public function __construct(protected UcpManager $manager) {}

    /**
     * GET /.well-known/agent-card.json
     */
    public function agentCard(): array
    {
        $capabilities = array_map(
            fn ($c) => ['name' => $c->name, 'version' => $c->version],
            $this->manager->capabilities()
        );

        return [
            'type' => 'agent-card',
            'name' => $this->manager->title(),
            'extensions' => [
                [
                    'uri' => 'https://ucp.dev/specification/reference?v='.$this->manager->version(),
                    'description' => 'Business agent supporting UCP Checkout',
                    'params' => ['capabilities' => $capabilities],
                ],
            ],
        ];
    }

    /**
     * POST /ucp/agent/message
     */
    public function handleMessage(array $body): array
    {
        $params = $body['params'] ?? [];
        $message = $params['message'] ?? $body['message'] ?? [];

        $contextId = $message['contextId'] ?? (string) Str::uuid();
        $parts = $message['parts'] ?? [];

        $dataPart = $this->findPart($parts, 'data');
        $textPart = $this->findPart($parts, 'text');

        if ($dataPart !== null) {
            $payload = $dataPart['data'] ?? [];
            $action = $payload['action'] ?? '';
        } elseif ($textPart !== null) {
            // Natural-language messages map to catalog search.
            $action = 'search_shopping_catalog';
            $payload = ['query' => $textPart['text'] ?? ''];
        } else {
            return $this->errorReply($body, 'No recognizable part found (text or data).');
        }

        $internalMethod = self::ACTION_MAP[$action] ?? null;

        if ($internalMethod === null) {
            return $this->errorReply($body, "Unknown action: {$action}");
        }

        try {
            $cleanParams = $payload;
            unset($cleanParams['action']);

            $sessionId = $cleanParams['id'] ?? $cleanParams['checkout_id'] ?? null;
            unset($cleanParams['id'], $cleanParams['checkout_id']);

            // Payment data may arrive under the A2A-namespaced key.
            if (isset($cleanParams['a2a.ucp.checkout.payment_data'])) {
                $cleanParams['payment'] = $cleanParams['a2a.ucp.checkout.payment_data'];
                unset($cleanParams['a2a.ucp.checkout.payment_data']);
            }

            $result = $this->manager->callHandler($internalMethod, $sessionId, $cleanParams);

            $resultData = is_object($result) && method_exists($result, 'toArray')
                ? $result->toArray()
                : $result;

            // Checkout/order payloads travel under the a2a.ucp.checkout key.
            $responseData = (isset($resultData['line_items']) || isset($resultData['id']))
                ? ['a2a.ucp.checkout' => $resultData]
                : $resultData;

            return [
                'jsonrpc' => '2.0',
                'id' => $body['id'] ?? null,
                'result' => [
                    'kind' => 'message',
                    'role' => 'agent',
                    'messageId' => (string) Str::uuid(),
                    'contextId' => $contextId,
                    'parts' => [
                        ['kind' => 'data', 'data' => $responseData],
                    ],
                ],
            ];
        } catch (UcpException $e) {
            return $this->errorReply($body, $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return $this->errorReply($body, $e->getMessage());
        }
    }

    protected function findPart(array $parts, string $type): ?array
    {
        foreach ($parts as $part) {
            if (($part['type'] ?? $part['kind'] ?? null) === $type) {
                return $part;
            }
        }

        return null;
    }

    public function errorReply(array $originalBody, string $errorMessage): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $originalBody['id'] ?? null,
            'error' => [
                'code' => -32603,
                'message' => $errorMessage,
            ],
        ];
    }
}
