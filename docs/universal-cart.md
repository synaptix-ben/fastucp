# Universal Cart

One persistent cart that can hold items from **this** merchant and any number of **remote UCP merchants** — inspired by Shopify's universal cart. With a single merchant it behaves like a normal cart; with several, checkout fans out into one UCP checkout session per merchant.

Enable it:

```php
// config/ucp.php
'universal_cart' => ['enabled' => true],
```

and run the migrations (`ucp_universal_cart_items` table).

## Cart identity

Priority order, resolved per request:

1. **`X-UCP-Cart-Id` header** — for headless agents with no browser session. Send the same value on every request.
2. **Authenticated user** — one persistent cart per user.
3. **Browser session** — guest carts.

## API

| Route | Purpose |
|---|---|
| `GET /ucp/cart` | Cart state grouped by merchant, with subtotals and a combined total |
| `GET /ucp/cart/view` | Rendered cart web component (Blade) |
| `POST /ucp/cart/items` | Add an item |
| `PATCH /ucp/cart/items/{id}` | Change quantity |
| `DELETE /ucp/cart/items/{id}` | Remove |
| `POST /ucp/cart/checkout` | Create checkout session(s) |

### Adding items

```http
POST /ucp/cart/items
{
  "merchant_url": "https://store-a.test",   // omit for items from THIS store
  "merchant_name": "Store A",
  "item_id": "sku_shoes",
  "title": "Running Shoes",
  "price": 12900,
  "quantity": 1,
  "image_url": "https://cdn.store-a.test/shoes.png",
  "currency": "USD"
}
```

Adding the same `item_id` from the same merchant again increments the quantity.

### Cart state

```json
{
  "cart_id": "client:my-agent-cart",
  "merchants": [
    { "merchant_url": null, "items": [...], "subtotal": 99900, "currency": "USD" },
    { "merchant_url": "https://store-a.test", "merchant_name": "Store A",
      "items": [...], "subtotal": 25800, "currency": "USD" }
  ],
  "item_count": 3,
  "total": 125700,
  "single_merchant": false
}
```

### Checkout fan-out

`POST /ucp/cart/checkout` groups items by merchant and, for each group, creates a UCP checkout session via `UcpClient` (local items go through your own UCP endpoint, so the flow is identical). The response lists one session per merchant:

```json
{
  "single_merchant": false,
  "sessions": [
    { "merchant_url": "https://store-a.test", "checkout_id": "chk_a",
      "status": "requires_escalation",
      "continue_url": "https://store-a.test/ucp/embedded-checkout/chk_a",
      "totals": [...], "currency": "USD" },
    { "merchant_url": null, "checkout_id": "chk_local", "status": "ready_for_complete", ... }
  ]
}
```

A merchant that fails to respond gets an `error` entry instead of killing the whole checkout.

**Pairing with Embedded Checkout:** each session with a `continue_url` can be rendered in its own iframe — the buyer completes each merchant's checkout inline, and the host supplies payment credentials once via ECP delegation. The bundled cart view emits a `ucp:cart:checkout` DOM event with the session list so your frontend can orchestrate this.

## Events

`FastUcp\Events\UniversalCartUpdated` fires on every mutation with the full cart and an action string (`added`, `updated`, `removed`, `checked_out`).
