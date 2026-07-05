# Security & Signing

## JWS response signing

UCP responses can carry a detached JWS signature in the `UCP-Signature` header so agents can verify integrity and authenticity. FastUCP signs with ES256.

### Setup

```bash
composer require web-token/jwt-framework
```

Generate a P-256 key (once):

```bash
php -r "
require 'vendor/autoload.php';
\$jwk = Jose\Component\KeyManagement\JWKFactory::createECKey('P-256', ['kid' => 'ucp-key-1', 'use' => 'sig', 'alg' => 'ES256']);
echo json_encode(\$jwk->jsonSerialize());
"
```

Store the full private JWK (with `d`) in your environment:

```env
UCP_SIGNING_ENABLED=true
UCP_SIGNING_KEY={"kty":"EC","crv":"P-256","kid":"ucp-key-1","d":"...","x":"...","y":"..."}
```

### What happens

- `UcpSigningMiddleware` signs every JSON response on the UCP routes and sets `UCP-Signature`.
- The discovery manifest automatically advertises the **public** portion of the key under `signing_keys` (private material is stripped), so verifiers can fetch it from `/.well-known/ucp`.
- Signing failures are reported to your logger and never break the response.

## Embedded checkout origin controls

Two layers protect the iframe channel:

1. **Server-side**: set an origin whitelist so only approved hosts can load the page:

   ```php
   'embedded' => [
       'allowed_origins' => ['https://gemini.google.com', 'https://agent.example'],
   ],
   ```

   Requests with an unlisted `ec_origin` receive a 403.

2. **Browser-side**: the ECP bridge validates the origin of every incoming `postMessage`, pins the host origin after the `ec.ready` handshake, and supports upgrading to a private `MessagePort` so payment credentials never traverse the window boundary.

## General hardening

- Put your UCP routes behind rate limiting (`RateLimiter` / `throttle` middleware) — they are unauthenticated by design.
- Verify payment credentials server-side with your PSP inside `completeCheckout`; never trust amounts or tokens from the client.
- Checkout sessions expire after 6 hours by default (cache store TTL); prune the database store with a scheduled job if you use it, dispatching `CheckoutExpired` for cleanup listeners.
- The `X-UCP-Cart-Id` header namespace (`client:`) is separate from user (`user:`) and session (`session:`) carts, so a client-supplied id can never collide with another user's cart.
