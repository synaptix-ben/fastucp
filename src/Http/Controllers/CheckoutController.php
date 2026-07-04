<?php

namespace FastUcp\Http\Controllers;

use FastUcp\Events\CheckoutCompleted;
use FastUcp\Events\CheckoutCreated;
use FastUcp\Events\CheckoutUpdated;
use FastUcp\Exceptions\UcpException;
use FastUcp\UcpManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class CheckoutController extends Controller
{
    public function __construct(protected UcpManager $manager) {}

    /**
     * POST /ucp/checkout-sessions
     */
    public function create(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'line_items' => ['required', 'array', 'min:1'],
            'line_items.*.item' => ['required', 'array'],
            'line_items.*.item.id' => ['required', 'string'],
            'line_items.*.quantity' => ['required', 'integer', 'min:1'],
            'buyer' => ['sometimes', 'nullable', 'array'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment' => ['sometimes', 'array'],
        ]);

        $checkout = $this->requireHandler()->createCheckout($payload);

        CheckoutCreated::dispatch($checkout);

        return response()->json($checkout->toArray(), 201);
    }

    /**
     * PATCH /ucp/checkout-sessions/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'line_items' => ['sometimes', 'array'],
            'buyer' => ['sometimes', 'nullable', 'array'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'payment' => ['sometimes', 'array'],
            'fulfillment' => ['sometimes', 'array'],
            'discounts' => ['sometimes', 'array'],
        ]);

        $checkout = $this->requireHandler()->updateCheckout($id, $payload);

        CheckoutUpdated::dispatch($checkout);

        return response()->json($checkout->toArray());
    }

    /**
     * POST /ucp/checkout-sessions/{id}/complete
     */
    public function complete(Request $request, string $id): JsonResponse
    {
        $payload = $request->validate([
            'payment' => ['required', 'array'],
        ]);

        $order = $this->requireHandler()->completeCheckout($id, $payload['payment']);

        CheckoutCompleted::dispatch($order, $id, $payload['payment']);

        return response()->json($order->toArray(), 201);
    }

    protected function requireHandler(): \FastUcp\Contracts\CheckoutHandler
    {
        return $this->manager->checkoutHandler()
            ?? throw new UcpException('invalid', 'No checkout handler registered', null, 'recoverable', 501);
    }
}
