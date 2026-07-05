# Protocols: MCP, A2A, Embedded Checkout

UCP is transport-agnostic. FastUCP serves the same registered handlers over REST (always on) plus any of three optional protocols, each toggled in `config/ucp.php`:

```php
'protocols' => [
    'mcp' => true,
    'a2a' => true,
    'embedded' => true,
],
```

Enabled protocols are advertised automatically in the `/.well-known/ucp` manifest so agents can pick their preferred transport.

---

## MCP (Model Context Protocol)

`POST /ucp/mcp` speaks JSON-RPC 2.0 with the standard MCP methods:

| Method | Behavior |
|---|---|
| `initialize` | Handshake; returns server info + tool capability |
| `tools/list` | Tool schemas derived from your registered handlers |
| `tools/call` | Bridges into the same handlers the REST routes use |

Exposed tools (when the matching handler is registered): `search_products`, `create_checkout`, `update_checkout`, `complete_checkout`.

Tool-level failures return `result.isError: true` per the MCP spec; protocol-level failures return JSON-RPC error objects. Successful calls include the payload both as a JSON text block and as `structuredContent`.

```bash
curl -X POST https://store.example.com/ucp/mcp -H 'Content-Type: application/json' -d '{
  "jsonrpc": "2.0", "id": 1, "method": "tools/call",
  "params": {"name": "create_checkout", "arguments": {
      "line_items": [{"item": {"id": "sku_tee"}, "quantity": 1}]}}
}'
```

---

## A2A (Agent-to-Agent)

Two endpoints:

- `GET /.well-known/agent-card.json` — the agent card listing your UCP capabilities
- `POST /ucp/agent/message` — the message handler

Messages carry **parts**. A `data` part maps its `action` onto a handler (`add_to_checkout`, `update_checkout`, `complete_checkout`, `search_shopping_catalog`); a `text` part is treated as a natural-language catalog search. Checkout payloads are returned under the `a2a.ucp.checkout` key of the reply's data part.

---

## Embedded Checkout Protocol (ECP)

ECP lets an agent surface (the **host**) render *your* checkout page inside an iframe/webview while delegating selected operations to its native UI. You stay merchant of record; the host provides authentication and, optionally, payment credentials and addresses.

### Flow

1. Your handler escalates: `$builder->setContinueUrl(route('ucp.embedded-checkout', $id))` → status `requires_escalation`.
2. The host opens `continue_url` in an iframe, appending `?ec_delegate=payment.credential,fulfillment.address_change` for the operations it wants to handle natively.
3. The server negotiates the requested delegates against what this checkout supports (`EmbeddedCheckoutSession::SUPPORTED_DELEGATES`) and fires the `EmbeddedCheckoutReady` event.
4. The page's JS bridge sends an `ec.ready` JSON-RPC request over `postMessage` with the accepted delegate list.
5. The host may respond with a `MessagePort`; all later traffic (including payment credentials) moves onto that private channel.

### Delegations

| Delegate | Request sent to host |
|---|---|
| `payment.credential` | `ec.payment.credential_request` → resolves with `{credential}` |
| `payment.instruments_change` | `ec.payment.instruments_change_request` |
| `fulfillment.address_change` | `ec.fulfillment.address_change_request` → resolves with `{address}` |

Notifications (no response expected): `ec.checkout.updated`, `ec.checkout.completed`, `ec.checkout.canceled`.

### The bridge

`resources/js/ecp-bridge.js` implements the full protocol: request/response correlation, timeouts, origin pinning (first valid responder, or the `ucp.embedded.allowed_origins` whitelist), MessagePort upgrade, and host-initiated method handling via `bridge.on(...)`. It is inlined into the default Blade view; publish `--tag=ucp-assets` to customize.

### Customizing the UI

- Publish and edit the Blade view: `php artisan vendor:publish --tag=ucp-views` → `resources/views/vendor/ucp/embedded-checkout.blade.php`.
- **Shopify merchants**: set `ucp.embedded.use_shopify_component` and `UCP_SHOPIFY_CHECKOUT_URL` to render Shopify's `<shopify-checkout>` Checkout Kit component instead of the packaged UI.

### Security

- Set `ucp.embedded.allowed_origins` to a whitelist in production — unlisted `ec_origin` values get a 403 and the bridge drops their messages.
- The bridge validates the origin of **every** incoming message and pins the host origin after the handshake.
