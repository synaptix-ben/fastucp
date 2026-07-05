{{--
    Universal Cart web component view.

    Shows the cart grouped by merchant with per-merchant subtotals and a
    combined total. Publish and customize with:
        php artisan vendor:publish --tag=ucp-views

    Receives:
        $cart array (from UniversalCart::toArray())
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your cart</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f6f6f7; color: #202223; padding: 16px; max-width: 520px; margin: 0 auto;
        }
        @media (prefers-color-scheme: dark) {
            body { background: #1a1a1c; color: #e3e3e5; }
            .merchant { background: #26262a !important; }
        }
        .merchant { background: #fff; border-radius: 12px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.06); }
        .merchant-name { font-weight: 700; font-size: 14px; margin-bottom: 8px; opacity: .85; }
        .item { display: flex; gap: 12px; align-items: center; padding: 8px 0; }
        .item img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; background: #eee; }
        .item .info { flex: 1; }
        .item .title { font-weight: 600; font-size: 14px; }
        .item .price { font-size: 13px; opacity: .7; }
        .item .line-total { font-weight: 600; font-size: 14px; }
        .qty-controls { display: flex; align-items: center; gap: 6px; }
        .qty-controls button {
            width: 26px; height: 26px; border-radius: 6px; border: 1px solid rgba(128,128,128,.4);
            background: transparent; color: inherit; cursor: pointer; font-size: 14px;
        }
        .subtotal { display: flex; justify-content: space-between; font-size: 14px; font-weight: 600; border-top: 1px solid rgba(128,128,128,.25); margin-top: 8px; padding-top: 10px; }
        .grand { display: flex; justify-content: space-between; font-weight: 700; font-size: 17px; padding: 12px 4px; }
        .btn {
            width: 100%; padding: 14px; border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
            background: #1a73e8; color: #fff; cursor: pointer;
        }
        .btn:disabled { opacity: .5; }
        .empty { text-align: center; padding: 48px 0; opacity: .6; }
        .badge { font-size: 11px; background: rgba(26,115,232,.12); color: #1a73e8; border-radius: 999px; padding: 2px 8px; margin-left: 6px; }
    </style>
</head>
<body>

<div id="ucp-cart">
    @if (empty($cart['merchants']))
        <div class="empty">Your cart is empty.</div>
    @else
        @foreach ($cart['merchants'] as $merchant)
            <div class="merchant">
                <div class="merchant-name">
                    {{ $merchant['merchant_name'] ?? ($merchant['merchant_url'] ? parse_url($merchant['merchant_url'], PHP_URL_HOST) : 'This store') }}
                    @if ($merchant['merchant_url'])
                        <span class="badge">external</span>
                    @endif
                </div>
                @foreach ($merchant['items'] as $item)
                    <div class="item" data-item-id="{{ $item['id'] }}">
                        @if (!empty($item['image_url']))
                            <img src="{{ $item['image_url'] }}" alt="">
                        @endif
                        <div class="info">
                            <div class="title">{{ $item['title'] }}</div>
                            <div class="price">{{ $item['currency'] }} {{ number_format($item['price'] / 100, 2) }}</div>
                        </div>
                        <div class="qty-controls">
                            <button data-action="decrement" @if($item['quantity'] <= 1) data-remove="1" @endif>&minus;</button>
                            <span>{{ $item['quantity'] }}</span>
                            <button data-action="increment">+</button>
                        </div>
                        <div class="line-total">{{ $item['currency'] }} {{ number_format($item['line_total'] / 100, 2) }}</div>
                    </div>
                @endforeach
                <div class="subtotal">
                    <span>Subtotal</span>
                    <span>{{ $merchant['currency'] }} {{ number_format($merchant['subtotal'] / 100, 2) }}</span>
                </div>
            </div>
        @endforeach

        <div class="grand">
            <span>Total ({{ $cart['item_count'] }} items)</span>
            <span>{{ number_format($cart['total'] / 100, 2) }}</span>
        </div>

        <button class="btn" id="ucp-cart-checkout">
            {{ $cart['single_merchant'] ? 'Check out' : 'Check out all stores' }}
        </button>
    @endif
</div>

<script>
    (function () {
        function api(method, url, body) {
            return fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: body ? JSON.stringify(body) : undefined,
            }).then(function (r) {
                if (!r.ok) throw new Error('Request failed: ' + r.status);
                return r.json();
            });
        }

        document.querySelectorAll('.item').forEach(function (row) {
            var itemId = row.getAttribute('data-item-id');
            var qtyEl = row.querySelector('.qty-controls span');

            row.querySelectorAll('button[data-action]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var qty = parseInt(qtyEl.textContent, 10);

                    if (btn.dataset.action === 'decrement' && btn.dataset.remove) {
                        api('DELETE', '/ucp/cart/items/' + itemId).then(function () { location.reload(); });
                        return;
                    }

                    var next = btn.dataset.action === 'increment' ? qty + 1 : Math.max(1, qty - 1);
                    api('PATCH', '/ucp/cart/items/' + itemId, { quantity: next })
                        .then(function () { location.reload(); });
                });
            });
        });

        var checkoutBtn = document.getElementById('ucp-cart-checkout');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function () {
                checkoutBtn.disabled = true;
                api('POST', '/ucp/cart/checkout', {})
                    .then(function (result) {
                        // Single merchant with an escalation URL: go straight there.
                        var withUrl = result.sessions.filter(function (s) { return s.continue_url; });
                        if (result.single_merchant && withUrl.length === 1) {
                            window.location.href = withUrl[0].continue_url;
                            return;
                        }
                        // Multi-merchant: hand the session list to the page for
                        // rendering (e.g. one embedded checkout per merchant).
                        document.dispatchEvent(new CustomEvent('ucp:cart:checkout', { detail: result }));
                        console.log('UCP checkout sessions', result);
                        checkoutBtn.disabled = false;
                    })
                    .catch(function (err) {
                        alert('Checkout failed: ' + err.message);
                        checkoutBtn.disabled = false;
                    });
            });
        }
    })();
</script>

</body>
</html>
