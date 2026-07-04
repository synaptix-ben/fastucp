<?php

namespace FastUcp;

use FastUcp\Contracts\CheckoutHandler;
use FastUcp\Contracts\DiscoveryHandler;
use FastUcp\Data\Capability;
use FastUcp\Data\DiscoveryProfile;
use FastUcp\Data\PaymentHandler;
use FastUcp\Data\UcpDiscoveryProfile;
use FastUcp\Data\UcpService;
use FastUcp\Exceptions\UcpException;

class UcpManager
{
    /** @var Capability[] */
    protected array $capabilities = [];

    /** @var PaymentHandler[] */
    protected array $paymentHandlers = [];

    protected ?CheckoutHandler $checkoutHandler = null;

    protected ?DiscoveryHandler $discoveryHandler = null;

    public function __construct(
        protected string $baseUrl,
        protected string $version,
        protected string $title = 'UCP Merchant',
        protected array $protocols = [],
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function version(): string
    {
        return $this->version;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function protocolEnabled(string $protocol): bool
    {
        return (bool) ($this->protocols[$protocol] ?? false);
    }

    // ------------------------------------------------------------------
    // Handler registration
    // ------------------------------------------------------------------

    public function registerCheckoutHandler(CheckoutHandler $handler): void
    {
        $this->checkoutHandler = $handler;

        $this->registerCapability(new Capability(
            name: 'dev.ucp.shopping.checkout',
            version: $this->version,
            spec: 'https://ucp.dev/specification/checkout',
            schema: 'https://ucp.dev/schemas/shopping/checkout.json',
        ));

        $this->registerCapability(new Capability(
            name: 'dev.ucp.shopping.order',
            version: $this->version,
            spec: 'https://ucp.dev/specification/order',
            schema: 'https://ucp.dev/schemas/shopping/order.json',
        ));
    }

    public function registerDiscoveryHandler(DiscoveryHandler $handler): void
    {
        $this->discoveryHandler = $handler;

        $this->registerCapability(new Capability(
            name: 'dev.ucp.shopping.discovery',
            version: $this->version,
            spec: 'https://ucp.dev/specification/discovery',
            schema: 'https://ucp.dev/schemas/shopping/discovery.json',
        ));
    }

    public function checkoutHandler(): ?CheckoutHandler
    {
        return $this->checkoutHandler;
    }

    public function discoveryHandler(): ?DiscoveryHandler
    {
        return $this->discoveryHandler;
    }

    public function registerCapability(Capability $capability): void
    {
        foreach ($this->capabilities as $existing) {
            if ($existing->name === $capability->name) {
                return;
            }
        }

        $this->capabilities[] = $capability;
    }

    /**
     * @return Capability[]
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function registerPaymentHandler(PaymentHandler $handler): void
    {
        $this->paymentHandlers[] = $handler;
    }

    /**
     * @return PaymentHandler[]
     */
    public function paymentHandlers(): array
    {
        return $this->paymentHandlers;
    }

    // ------------------------------------------------------------------
    // Protocol bridge — MCP / A2A call into the same registered handlers
    // ------------------------------------------------------------------

    /**
     * Bridge for other protocols (MCP, A2A) to invoke registered handlers
     * with a consistent method-name registry.
     */
    public function callHandler(string $method, ?string $sessionId, array $params): mixed
    {
        return match ($method) {
            'create_checkout' => $this->requireCheckoutHandler()->createCheckout($params),
            'update_checkout' => $this->requireCheckoutHandler()->updateCheckout(
                $sessionId ?? throw new UcpException('missing', 'Session id is required', '$.id'),
                $params,
            ),
            'complete_checkout' => $this->requireCheckoutHandler()->completeCheckout(
                $sessionId ?? throw new UcpException('missing', 'Session id is required', '$.id'),
                $params['payment'] ?? $params,
            ),
            'search_products' => $this->requireDiscoveryHandler()->search($params['query'] ?? ''),
            default => throw new UcpException('invalid', "Unknown method: {$method}", null, 'recoverable', 404),
        };
    }

    /**
     * Tool definitions for the MCP tools/list response, derived from the
     * registered handlers rather than reflection (bug fix: the Python
     * version keyed discovery handlers by function name, making the tool
     * name unpredictable).
     *
     * @return array<int, array{name: string, description: string, inputSchema: array}>
     */
    public function toolDefinitions(): array
    {
        $tools = [];

        if ($this->discoveryHandler !== null) {
            $tools[] = [
                'name' => 'search_products',
                'description' => 'Search the product catalog.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Search query'],
                    ],
                    'required' => ['query'],
                ],
            ];
        }

        if ($this->checkoutHandler !== null) {
            $tools[] = [
                'name' => 'create_checkout',
                'description' => 'Create a new checkout session with line items.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'line_items' => [
                            'type' => 'array',
                            'description' => 'Items to check out: [{item: {id}, quantity}]',
                            'items' => ['type' => 'object'],
                        ],
                        'buyer' => ['type' => 'object', 'description' => 'Buyer information'],
                        'currency' => ['type' => 'string', 'description' => 'ISO 4217 currency code'],
                    ],
                    'required' => ['line_items'],
                ],
            ];
            $tools[] = [
                'name' => 'update_checkout',
                'description' => 'Update an existing checkout session (buyer info, shipping, etc).',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Checkout session id'],
                        'buyer' => ['type' => 'object', 'description' => 'Buyer information'],
                        'line_items' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                    'required' => ['id'],
                ],
            ];
            $tools[] = [
                'name' => 'complete_checkout',
                'description' => 'Complete a checkout session with payment data, creating an order.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'Checkout session id'],
                        'payment' => ['type' => 'object', 'description' => 'Payment data (token, type)'],
                    ],
                    'required' => ['id', 'payment'],
                ],
            ];
        }

        return $tools;
    }

    // ------------------------------------------------------------------
    // Discovery manifest + UCP response contexts
    // ------------------------------------------------------------------

    public function buildManifest(): UcpDiscoveryProfile
    {
        $rest = [
            'schema' => 'https://ucp.dev/services/shopping/rest.openapi.json',
            'endpoint' => $this->baseUrl,
        ];

        $mcp = $this->protocolEnabled('mcp') ? [
            'schema' => 'https://ucp.dev/services/shopping/mcp.openrpc.json',
            'endpoint' => $this->baseUrl.'/ucp/mcp',
        ] : null;

        $a2a = $this->protocolEnabled('a2a') ? [
            'endpoint' => $this->baseUrl.'/.well-known/agent-card.json',
        ] : null;

        // Embedded is per-capability (via continue_url), so the service
        // binding only advertises the protocol schema.
        $embedded = $this->protocolEnabled('embedded') ? [
            'schema' => 'https://ucp.dev/services/shopping/embedded.openrpc.json',
        ] : null;

        $profile = new DiscoveryProfile(
            version: $this->version,
            services: [
                'dev.ucp.shopping' => new UcpService(
                    version: $this->version,
                    spec: 'https://ucp.dev/specification/overview',
                    rest: $rest,
                    mcp: $mcp,
                    a2a: $a2a,
                    embedded: $embedded,
                ),
            ],
            capabilities: $this->capabilities,
        );

        return new UcpDiscoveryProfile(
            ucp: $profile,
            paymentHandlers: $this->paymentHandlers !== [] ? $this->paymentHandlers : null,
            signingKeys: $this->publicSigningKeys(),
        );
    }

    /**
     * UCP context block for checkout/order responses (version + active capabilities).
     */
    public function createUcpContext(): array
    {
        return [
            'version' => $this->version,
            'capabilities' => array_map(
                fn (Capability $c) => ['name' => $c->name, 'version' => $c->version],
                $this->capabilities
            ),
        ];
    }

    /**
     * Public JWK signing keys advertised in the discovery profile, derived
     * from the configured private key (private parts stripped).
     *
     * @return \FastUcp\Data\SigningKey[]|null
     */
    protected function publicSigningKeys(): ?array
    {
        if (! config('ucp.signing.enabled') || ! config('ucp.signing.key')) {
            return null;
        }

        $jwk = json_decode((string) config('ucp.signing.key'), true);
        if (! is_array($jwk) || ! isset($jwk['kty'])) {
            return null;
        }

        // Strip private key material — only public parts are advertised.
        unset($jwk['d'], $jwk['p'], $jwk['q'], $jwk['dp'], $jwk['dq'], $jwk['qi'], $jwk['k']);

        return [\FastUcp\Data\SigningKey::fromArray($jwk + ['kid' => $jwk['kid'] ?? 'ucp-signing-key'])];
    }

    // ------------------------------------------------------------------

    protected function requireCheckoutHandler(): CheckoutHandler
    {
        return $this->checkoutHandler
            ?? throw new UcpException('invalid', 'No checkout handler registered', null, 'recoverable', 501);
    }

    protected function requireDiscoveryHandler(): DiscoveryHandler
    {
        return $this->discoveryHandler
            ?? throw new UcpException('invalid', 'No discovery handler registered', null, 'recoverable', 501);
    }
}
