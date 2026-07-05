<?php

namespace FastUcp\Client\Transports;

use FastUcp\Exceptions\UcpException;
use Illuminate\Support\Facades\Http;

class McpTransport
{
    protected int $requestId = 1;

    public function __construct(
        protected string $baseUrl,
        protected string $mcpPath = '/ucp/mcp',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setEndpoint(string $url): void
    {
        $this->baseUrl = rtrim($url, '/');
        $this->mcpPath = '';
    }

    public function createCheckout(array $payload): array
    {
        return $this->callTool('create_checkout', $payload);
    }

    public function updateCheckout(string $sessionId, array $payload): array
    {
        return $this->callTool('update_checkout', ['id' => $sessionId] + $payload);
    }

    public function completeCheckout(string $sessionId, array $payment): array
    {
        return $this->callTool('complete_checkout', ['id' => $sessionId, 'payment' => $payment]);
    }

    public function searchProducts(string $query): array
    {
        return $this->callTool('search_products', ['query' => $query]);
    }

    public function listTools(): array
    {
        return $this->send('tools/list', [])['tools'] ?? [];
    }

    public function callTool(string $toolName, array $arguments): array
    {
        $result = $this->send('tools/call', [
            'name' => $toolName,
            'arguments' => $arguments,
        ]);

        if ($result['isError'] ?? false) {
            $text = $result['content'][0]['text'] ?? 'Unknown MCP error';

            throw new UcpException('mcp_tool_error', "MCP tool '{$toolName}' failed: {$text}", null, 'recoverable', 502);
        }

        if (isset($result['structuredContent'])) {
            return $result['structuredContent'];
        }

        // Fall back to parsing the text content block.
        $text = $result['content'][0]['text'] ?? null;
        if ($text !== null) {
            $decoded = json_decode($text, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $result;
    }

    protected function send(string $method, array $params): array
    {
        $response = Http::acceptJson()->post($this->baseUrl.$this->mcpPath, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->requestId++,
        ]);

        if ($response->failed()) {
            throw new UcpException('mcp_transport_error', "MCP request failed with status {$response->status()}", null, 'recoverable', 502);
        }

        $body = $response->json();

        if (isset($body['error'])) {
            throw new UcpException(
                'mcp_error',
                'MCP error '.($body['error']['code'] ?? '').': '.($body['error']['message'] ?? 'unknown'),
                null,
                'recoverable',
                502,
            );
        }

        return $body['result'] ?? [];
    }
}
