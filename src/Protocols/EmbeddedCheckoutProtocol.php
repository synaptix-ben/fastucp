<?php

namespace FastUcp\Protocols;

use FastUcp\Contracts\SessionStore;
use FastUcp\Data\EmbeddedCheckoutSession;
use FastUcp\Events\EmbeddedCheckoutReady;
use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;

/**
 * Embedded Checkout Protocol (ECP): the merchant's checkout UI runs inside
 * the host's iframe/webview and exchanges JSON-RPC 2.0 messages with the
 * host over postMessage. This class handles the server-side pieces:
 * session lookup, delegation negotiation, and view payload assembly.
 * The browser-side protocol lives in resources/js/ecp-bridge.js.
 */
class EmbeddedCheckoutProtocol
{
    public function __construct(
        protected UcpManager $manager,
        protected SessionStore $store,
    ) {}

    /**
     * Resolve everything the embedded checkout view needs.
     *
     * @param string|null $ecDelegateParam Raw ?ec_delegate= query value
     */
    public function prepareView(string $sessionId, ?string $ecDelegateParam, ?string $hostOrigin = null): array
    {
        $checkout = $this->store->get($sessionId);

        if ($checkout === null) {
            throw new UcpException('not_found', "Checkout session not found: {$sessionId}", null, 'recoverable', 404);
        }

        $ecSession = EmbeddedCheckoutSession::negotiate($sessionId, $ecDelegateParam, $hostOrigin);

        if (! $this->originAllowed($hostOrigin)) {
            throw new UcpException('forbidden', 'Host origin is not allowed to embed this checkout.', null, 'recoverable', 403);
        }

        EmbeddedCheckoutReady::dispatch($ecSession);

        return [
            'checkout' => $checkout,
            'ecSession' => $ecSession,
            'useShopifyComponent' => (bool) config('ucp.embedded.use_shopify_component'),
            'shopifyCheckoutUrl' => config('ucp.embedded.shopify_checkout_url'),
            'allowedOrigins' => config('ucp.embedded.allowed_origins'),
        ];
    }

    /**
     * Build the continue_url that escalating checkouts should return,
     * including the negotiated delegate list for the host to re-request.
     */
    public function continueUrl(string $sessionId): string
    {
        return $this->manager->baseUrl().'/ucp/embedded-checkout/'.$sessionId;
    }

    protected function originAllowed(?string $origin): bool
    {
        $allowed = config('ucp.embedded.allowed_origins');

        if ($allowed === null || $origin === null) {
            return true;
        }

        return in_array($origin, (array) $allowed, true);
    }
}
