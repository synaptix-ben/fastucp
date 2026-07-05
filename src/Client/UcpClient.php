<?php

namespace FastUcp\Client;

use FastUcp\Client\Transports\A2aTransport;
use FastUcp\Client\Transports\McpTransport;
use FastUcp\Client\Transports\RestTransport;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Order;
use FastUcp\Data\UcpDiscoveryProfile;
use FastUcp\Exceptions\UcpException;

/**
 * Multi-transport client for consuming UCP merchant servers.
 *
 *   $client = new UcpClient('https://store.example.com');
 *   $manifest = $client->discover();
 *   $checkout = $client->createCheckout([['item' => ['id' => 'sku_1'], 'quantity' => 1]]);
 */
class UcpClient
{
    protected RestTransport $rest;

    protected ?McpTransport $mcp = null;

    protected ?A2aTransport $a2a = null;

    protected ?UcpDiscoveryProfile $manifest = null;

    public function __construct(
        protected string $baseUrl,
        protected string $transport = 'rest',
        protected ?string $currency = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->currency ??= config('ucp.currency', 'USD');

        if (! in_array($transport, ['rest', 'mcp', 'a2a'], true)) {
            throw new \InvalidArgumentException("Unknown transport: {$transport}. Use 'rest', 'mcp', or 'a2a'.");
        }

        $this->rest = new RestTransport($this->baseUrl);

        if ($transport === 'mcp') {
            $this->mcp = new McpTransport($this->baseUrl);
        } elseif ($transport === 'a2a') {
            $this->a2a = new A2aTransport($this->baseUrl);
        }
    }

    /**
     * Fetch the /.well-known/ucp discovery profile and rewire transport
     * endpoints to those the merchant advertises.
     */
    public function discover(): UcpDiscoveryProfile
    {
        $data = $this->rest->getManifest();
        $this->manifest = UcpDiscoveryProfile::fromArray($data);

        $shopping = $this->manifest->ucp->services['dev.ucp.shopping'] ?? null;

        if ($shopping !== null) {
            if (isset($shopping->rest['endpoint'])) {
                // The advertised REST endpoint is the API base; checkout
                // sessions live under /ucp/checkout-sessions relative to it
                // unless it already points at a full path.
                $this->rest = new RestTransport($shopping->rest['endpoint']);
            }
            if ($this->mcp !== null && isset($shopping->mcp['endpoint'])) {
                $this->mcp->setEndpoint($shopping->mcp['endpoint']);
            }
        }

        return $this->manifest;
    }

    public function manifest(): ?UcpDiscoveryProfile
    {
        return $this->manifest;
    }

    /**
     * @param array<int, array{item: array{id: string}, quantity: int}> $lineItems
     */
    public function createCheckout(array $lineItems, array $buyer = [], ?string $currency = null): CheckoutResponse
    {
        $payload = [
            'line_items' => $lineItems,
            'currency' => $currency ?? $this->currency,
            'payment' => (object) [],
        ];

        if ($buyer !== []) {
            $payload['buyer'] = $buyer;
        }

        $data = match ($this->transport) {
            'rest' => $this->rest->createCheckout($payload),
            'mcp' => $this->requireMcp()->createCheckout($payload),
            'a2a' => $this->requireA2a()->createCheckout($payload),
        };

        return CheckoutResponse::fromArray($data);
    }

    public function updateCheckout(string $sessionId, array $payload): CheckoutResponse
    {
        $data = match ($this->transport) {
            'rest' => $this->rest->updateCheckout($sessionId, $payload),
            'mcp' => $this->requireMcp()->updateCheckout($sessionId, $payload),
            'a2a' => $this->requireA2a()->updateCheckout($sessionId, $payload),
        };

        return CheckoutResponse::fromArray($data);
    }

    public function completeCheckout(string $sessionId, array $payment): Order
    {
        $data = match ($this->transport) {
            'rest' => $this->rest->completeCheckout($sessionId, $payment),
            'mcp' => $this->requireMcp()->completeCheckout($sessionId, $payment),
            'a2a' => $this->requireA2a()->completeCheckout($sessionId, $payment),
        };

        return Order::fromArray($data);
    }

    /**
     * Search the merchant's catalog (MCP and A2A transports).
     */
    public function searchProducts(string $query): array
    {
        return match ($this->transport) {
            'mcp' => $this->requireMcp()->searchProducts($query),
            'a2a' => $this->requireA2a()->sendText($query),
            default => throw new UcpException(
                'unsupported',
                'Product search over REST is merchant-specific; use the MCP or A2A transport.',
                null,
                'recoverable',
                501,
            ),
        };
    }

    protected function requireMcp(): McpTransport
    {
        return $this->mcp ??= new McpTransport($this->baseUrl);
    }

    protected function requireA2a(): A2aTransport
    {
        return $this->a2a ??= new A2aTransport($this->baseUrl);
    }
}
