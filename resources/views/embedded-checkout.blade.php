{{--
    UCP Embedded Checkout view.

    Rendered inside the host's iframe/webview. Publish and customize with:
        php artisan vendor:publish --tag=ucp-views

    Receives:
        $checkout            array  Checkout session data
        $ecSession           \FastUcp\Data\EmbeddedCheckoutSession
        $useShopifyComponent bool   Render Shopify's <shopify-checkout> instead
        $shopifyCheckoutUrl  ?string
        $allowedOrigins      ?array
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f6f6f7; color: #202223; padding: 16px; max-width: 480px; margin: 0 auto;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #1a1a1c; color: #e3e3e5; }
            .card { background: #26262a !important; }
        }
        .card { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .line-item { display: flex; gap: 12px; align-items: center; padding: 8px 0; }
        .line-item img { width: 56px; height: 56px; border-radius: 8px; object-fit: cover; background: #eee; }
        .line-item .info { flex: 1; }
        .line-item .title { font-weight: 600; font-size: 14px; }
        .line-item .qty { font-size: 12px; opacity: .7; }
        .line-item .price { font-weight: 600; font-size: 14px; }
        .totals-row { display: flex; justify-content: space-between; padding: 4px 0; font-size: 14px; }
        .totals-row.grand { font-weight: 700; font-size: 16px; border-top: 1px solid rgba(128,128,128,.25); margin-top: 8px; padding-top: 12px; }
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
            background: #1a73e8; color: #fff; cursor: pointer; margin-top: 8px;
        }
        .btn:disabled { opacity: .5; cursor: not-allowed; }
        .btn.secondary { background: transparent; color: inherit; border: 1px solid rgba(128,128,128,.4); }
        .messages { color: #b3261e; font-size: 13px; margin-bottom: 12px; }
        h2 { font-size: 15px; margin-bottom: 8px; }
        .shipping-option { display: flex; align-items: center; gap: 8px; padding: 6px 0; font-size: 14px; }
        .links { text-align: center; font-size: 12px; margin-top: 12px; opacity: .7; }
        .links a { color: inherit; margin: 0 6px; }
    </style>
</head>
<body>

@if ($useShopifyComponent && $shopifyCheckoutUrl)
    {{-- Shopify merchants: delegate the entire UI to Shopify's Checkout Kit component. --}}
    <script type="module" src="https://cdn.shopify.com/shopifycloud/checkout-kit/checkout-kit.js"></script>
    <shopify-checkout src="{{ $shopifyCheckoutUrl }}"></shopify-checkout>
@else
    <div id="ucp-checkout" data-session-id="{{ $ecSession->sessionId }}">
        @if (!empty($checkout['messages']))
            <div class="messages">
                @foreach ($checkout['messages'] as $message)
                    @if (($message['type'] ?? '') === 'error')
                        <div>&#9888; {{ $message['content'] }}</div>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="card">
            <h2>Order summary</h2>
            @foreach ($checkout['line_items'] ?? [] as $lineItem)
                <div class="line-item">
                    @if (!empty($lineItem['item']['image_url']))
                        <img src="{{ $lineItem['item']['image_url'] }}" alt="">
                    @endif
                    <div class="info">
                        <div class="title">{{ $lineItem['item']['title'] ?? $lineItem['item']['id'] }}</div>
                        <div class="qty">Qty {{ $lineItem['quantity'] }}</div>
                    </div>
                    <div class="price">
                        {{ $checkout['currency'] }} {{ number_format(($lineItem['totals'][0]['amount'] ?? 0) / 100, 2) }}
                    </div>
                </div>
            @endforeach
        </div>

        @php
            $shippingGroups = $checkout['fulfillment']['methods'][0]['groups'] ?? [];
        @endphp
        @if (!empty($shippingGroups[0]['options']))
            <div class="card">
                <h2>Delivery</h2>
                @foreach ($shippingGroups[0]['options'] as $option)
                    <label class="shipping-option">
                        <input type="radio" name="shipping" value="{{ $option['id'] }}"
                            @checked(($shippingGroups[0]['selected_option_id'] ?? null) === $option['id'])>
                        <span>{{ $option['title'] }}</span>
                        <span style="margin-left:auto">
                            {{ $checkout['currency'] }} {{ number_format(($option['totals'][0]['amount'] ?? 0) / 100, 2) }}
                        </span>
                    </label>
                @endforeach
            </div>
        @endif

        <div class="card">
            @foreach ($checkout['totals'] ?? [] as $total)
                <div class="totals-row {{ $total['type'] === 'total' ? 'grand' : '' }}">
                    <span>{{ $total['display_text'] ?? ucfirst(str_replace('_', ' ', $total['type'])) }}</span>
                    <span>{{ $checkout['currency'] }} {{ number_format($total['amount'] / 100, 2) }}</span>
                </div>
            @endforeach
        </div>

        @if ($ecSession->acceptedDelegates !== [])
            <button class="btn secondary" id="ucp-change-address" hidden>Change delivery address</button>
        @endif
        <button class="btn" id="ucp-pay">Pay now</button>

        <div class="links">
            @foreach ($checkout['links'] ?? [] as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener">
                    {{ $link['title'] ?? ucwords(str_replace('_', ' ', $link['type'])) }}
                </a>
            @endforeach
        </div>
    </div>

    <script>{!! file_get_contents(__DIR__.'/../js/ecp-bridge.js') !!}</script>
    <script>
        (function () {
            var checkout = @json($checkout);
            var bridge = new UcpEcpBridge({
                acceptedDelegates: @json($ecSession->acceptedDelegates),
                allowedOrigins: @json($allowedOrigins),
            });

            bridge.connect().then(function () {
                // Reveal delegated affordances once the host is connected.
                if (bridge.isDelegated('fulfillment.address_change')) {
                    var btn = document.getElementById('ucp-change-address');
                    if (btn) btn.hidden = false;
                }
            }).catch(function (err) {
                console.warn('ECP handshake failed; running standalone.', err);
            });

            var addressBtn = document.getElementById('ucp-change-address');
            if (addressBtn) {
                addressBtn.addEventListener('click', function () {
                    bridge.requestAddressChange({ checkout_id: checkout.id })
                        .then(function (result) {
                            // PATCH the new address back to the merchant server.
                            return fetch('/ucp/checkout-sessions/' + encodeURIComponent(checkout.id), {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ fulfillment: { address: result.address } }),
                            }).then(function (r) { return r.json(); });
                        })
                        .then(function (updated) {
                            bridge.notifyUpdated(updated);
                            window.location.reload();
                        })
                        .catch(function (err) { console.warn('Address change canceled', err); });
                });
            }

            document.getElementById('ucp-pay').addEventListener('click', function () {
                var payBtn = this;
                payBtn.disabled = true;

                var credentialPromise;
                if (bridge.ready && bridge.isDelegated('payment.credential')) {
                    var grandTotal = (checkout.totals || []).filter(function (t) { return t.type === 'total'; })[0];
                    credentialPromise = bridge.requestPaymentCredential({
                        checkout_id: checkout.id,
                        total: grandTotal ? grandTotal.amount : 0,
                        currency: checkout.currency,
                    }).then(function (result) { return result.credential; });
                } else {
                    // Standalone/no delegation: merchants replace this branch
                    // with their own payment collection UI.
                    credentialPromise = Promise.reject(new Error('No payment.credential delegation and no local payment UI configured.'));
                }

                credentialPromise
                    .then(function (credential) {
                        return fetch('/ucp/checkout-sessions/' + encodeURIComponent(checkout.id) + '/complete', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ payment: credential }),
                        }).then(function (r) {
                            if (!r.ok) throw new Error('Complete failed: ' + r.status);
                            return r.json();
                        });
                    })
                    .then(function (order) {
                        bridge.notifyCompleted(order);
                        document.getElementById('ucp-checkout').innerHTML =
                            '<div class="card" style="text-align:center;padding:32px">' +
                            '<div style="font-size:40px">&#127881;</div>' +
                            '<h2>Order confirmed</h2>' +
                            '<p style="font-size:13px;opacity:.7">' + order.id + '</p></div>';
                    })
                    .catch(function (err) {
                        console.error(err);
                        payBtn.disabled = false;
                        alert('Payment failed: ' + err.message);
                    });
            });
        })();
    </script>
@endif

</body>
</html>
