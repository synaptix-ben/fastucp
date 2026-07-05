<?php

namespace FastUcp\Http\Controllers;

use FastUcp\Client\UcpClient;
use FastUcp\Data\UniversalCart;
use FastUcp\Events\UniversalCartUpdated;
use FastUcp\Models\UcpUniversalCartItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Universal Cart: one persistent cart that can hold items from this
 * merchant and any number of remote UCP merchants. With a single merchant
 * it behaves as a normal cart; with several, checkout fans out into one
 * UCP checkout session per merchant.
 */
class UniversalCartController extends Controller
{
    /**
     * GET /ucp/cart
     */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->loadCart($request)->toArray());
    }

    /**
     * GET /ucp/cart/view — renders the cart web component.
     */
    public function view(Request $request): View
    {
        return view('ucp::universal-cart', [
            'cart' => $this->loadCart($request)->toArray(),
        ]);
    }

    /**
     * POST /ucp/cart/items
     *
     * merchant_url omitted/null means "this merchant" (local item).
     */
    public function addItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'merchant_url' => ['sometimes', 'nullable', 'url'],
            'merchant_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'item_id' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'price' => ['required', 'integer', 'min:0'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'image_url' => ['sometimes', 'nullable', 'url'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $cartId = $this->cartId($request);
        $merchantUrl = isset($data['merchant_url']) ? rtrim($data['merchant_url'], '/') : null;

        // Same item from the same merchant: bump the quantity.
        $existing = UcpUniversalCartItem::where('cart_id', $cartId)
            ->where('merchant_url', $merchantUrl)
            ->where('item_id', $data['item_id'])
            ->first();

        if ($existing !== null) {
            $existing->increment('quantity', $data['quantity'] ?? 1);
        } else {
            UcpUniversalCartItem::create([
                'cart_id' => $cartId,
                'merchant_url' => $merchantUrl,
                'merchant_name' => $data['merchant_name'] ?? null,
                'item_id' => $data['item_id'],
                'title' => $data['title'],
                'price' => $data['price'],
                'quantity' => $data['quantity'] ?? 1,
                'image_url' => $data['image_url'] ?? null,
                'currency' => $data['currency'] ?? config('ucp.currency', 'USD'),
                'metadata' => $data['metadata'] ?? null,
            ]);
        }

        $cart = $this->loadCart($request);
        UniversalCartUpdated::dispatch($cart, 'added');

        return response()->json($cart->toArray(), 201);
    }

    /**
     * PATCH /ucp/cart/items/{id}
     */
    public function updateItem(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        UcpUniversalCartItem::where('cart_id', $this->cartId($request))
            ->whereKey($id)
            ->firstOrFail()
            ->update(['quantity' => $data['quantity']]);

        $cart = $this->loadCart($request);
        UniversalCartUpdated::dispatch($cart, 'updated');

        return response()->json($cart->toArray());
    }

    /**
     * DELETE /ucp/cart/items/{id}
     */
    public function removeItem(Request $request, string $id): JsonResponse
    {
        UcpUniversalCartItem::where('cart_id', $this->cartId($request))
            ->whereKey($id)
            ->firstOrFail()
            ->delete();

        $cart = $this->loadCart($request);
        UniversalCartUpdated::dispatch($cart, 'removed');

        return response()->json($cart->toArray());
    }

    /**
     * POST /ucp/cart/checkout
     *
     * Fans out to each merchant in the cart, creating one UCP checkout
     * session per merchant. Local items (merchant_url null) use the local
     * checkout handler through the same REST surface.
     */
    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate([
            'buyer' => ['sometimes', 'nullable', 'array'],
        ]);

        $cart = $this->loadCart($request);

        if ($cart->items === []) {
            return response()->json(['error' => 'Cart is empty'], 422);
        }

        $sessions = [];

        foreach ($cart->groupedByMerchant() as $merchantKey => $items) {
            $merchantUrl = $merchantKey === 'self' ? config('ucp.base_url') : $merchantKey;

            $lineItems = array_map(fn ($item) => [
                'item' => ['id' => $item->itemId],
                'quantity' => $item->quantity,
            ], $items);

            try {
                $client = new UcpClient($merchantUrl);
                $checkout = $client->createCheckout(
                    lineItems: $lineItems,
                    buyer: $data['buyer'] ?? [],
                    currency: $items[0]->currency,
                );

                $sessions[] = [
                    'merchant_url' => $merchantKey === 'self' ? null : $merchantUrl,
                    'merchant_name' => $items[0]->merchantName,
                    'checkout_id' => $checkout->id,
                    'status' => $checkout->status,
                    'continue_url' => $checkout->continueUrl,
                    'totals' => array_map(fn ($t) => $t->toArray(), $checkout->totals),
                    'currency' => $checkout->currency,
                ];
            } catch (Throwable $e) {
                report($e);

                $sessions[] = [
                    'merchant_url' => $merchantKey === 'self' ? null : $merchantUrl,
                    'merchant_name' => $items[0]->merchantName,
                    'error' => $e->getMessage(),
                ];
            }
        }

        UniversalCartUpdated::dispatch($cart, 'checked_out');

        return response()->json([
            'cart_id' => $cart->cartId,
            'single_merchant' => $cart->isSingleMerchant(),
            'sessions' => $sessions,
        ], 201);
    }

    protected function loadCart(Request $request): UniversalCart
    {
        $cartId = $this->cartId($request);

        $items = UcpUniversalCartItem::where('cart_id', $cartId)
            ->orderBy('created_at')
            ->get()
            ->map(fn (UcpUniversalCartItem $model) => $model->toData())
            ->all();

        return new UniversalCart($cartId, $items);
    }

    /**
     * Authenticated users get a stable per-user cart; guests fall back to
     * their session id, so the cart persists across page loads.
     */
    protected function cartId(Request $request): string
    {
        if ($request->user() !== null) {
            return 'user:'.$request->user()->getAuthIdentifier();
        }

        return 'session:'.$request->session()->getId();
    }
}
