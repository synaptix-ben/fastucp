<?php

namespace FastUcp\Client\Transports;

use FastUcp\Exceptions\UcpException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class A2aTransport
{
    public function __construct(
        protected string $baseUrl,
        protected string $messagePath = '/ucp/agent/message',
        protected ?string $agentProfile = null,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function createCheckout(array $payload): array
    {
        return $this->sendAction('add_to_checkout', $payload);
    }

    public function updateCheckout(string $sessionId, array $payload): array
    {
        return $this->sendAction('update_checkout', ['id' => $sessionId] + $payload);
    }

    public function completeCheckout(string $sessionId, array $payment): array
    {
        return $this->sendAction('complete_checkout', ['id' => $sessionId, 'payment' => $payment]);
    }

    public function sendText(string $text): array
    {
        return $this->send([
            ['kind' => 'text', 'text' => $text],
        ]);
    }

    public function sendAction(string $action, array $data): array
    {
        return $this->send([
            ['kind' => 'data', 'data' => ['action' => $action] + $data],
        ]);
    }

    protected function send(array $parts): array
    {
        $headers = [];
        if ($this->agentProfile !== null) {
            $headers['UCP-Agent'] = 'profile="'.$this->agentProfile.'"';
        }

        $response = Http::acceptJson()->withHeaders($headers)->post($this->baseUrl.$this->messagePath, [
            'jsonrpc' => '2.0',
            'id' => (string) Str::uuid(),
            'params' => [
                'message' => [
                    'role' => 'user',
                    'kind' => 'message',
                    'messageId' => (string) Str::uuid(),
                    'parts' => $parts,
                ],
            ],
        ]);

        $body = $response->json() ?? [];

        if (isset($body['error'])) {
            throw new UcpException(
                'a2a_error',
                'A2A error: '.($body['error']['message'] ?? 'unknown'),
                null,
                'recoverable',
                502,
            );
        }

        if ($response->failed()) {
            throw new UcpException('a2a_transport_error', "A2A request failed with status {$response->status()}", null, 'recoverable', 502);
        }

        // Unwrap the data part; checkout payloads sit under a2a.ucp.checkout.
        $parts = $body['result']['parts'] ?? [];
        foreach ($parts as $part) {
            if (($part['kind'] ?? $part['type'] ?? null) === 'data') {
                $data = $part['data'] ?? [];

                return $data['a2a.ucp.checkout'] ?? $data;
            }
        }

        return $body['result'] ?? [];
    }
}
