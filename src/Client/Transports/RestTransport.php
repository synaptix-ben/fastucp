<?php

namespace FastUcp\Client\Transports;

use FastUcp\Exceptions\UcpException;
use Illuminate\Support\Facades\Http;

class RestTransport
{
    public function __construct(
        protected string $baseUrl,
        protected string $checkoutPath = '/ucp/checkout-sessions',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function setCheckoutEndpoint(string $url): void
    {
        // Absolute endpoint discovered from the manifest.
        $this->baseUrl = rtrim($url, '/');
        $this->checkoutPath = '';
    }

    public function createCheckout(array $payload): array
    {
        return $this->request('post', $this->checkoutUrl(), $payload);
    }

    public function updateCheckout(string $sessionId, array $payload): array
    {
        return $this->request('patch', $this->checkoutUrl().'/'.$sessionId, $payload);
    }

    public function completeCheckout(string $sessionId, array $payment): array
    {
        return $this->request('post', $this->checkoutUrl().'/'.$sessionId.'/complete', ['payment' => $payment]);
    }

    public function getManifest(): array
    {
        return $this->request('get', $this->baseUrl.'/.well-known/ucp');
    }

    protected function checkoutUrl(): string
    {
        return $this->baseUrl.$this->checkoutPath;
    }

    protected function request(string $method, string $url, array $payload = []): array
    {
        $response = $method === 'get'
            ? Http::acceptJson()->get($url)
            : Http::acceptJson()->{$method}($url, $payload);

        if ($response->failed()) {
            $messages = $response->json('messages.0');

            throw new UcpException(
                errorCode: $messages['code'] ?? 'request_failed',
                message: $messages['content'] ?? "UCP request failed: {$response->status()} {$url}",
                path: $messages['path'] ?? null,
                severity: $messages['severity'] ?? 'recoverable',
                statusCode: $response->status(),
            );
        }

        return $response->json() ?? [];
    }
}
