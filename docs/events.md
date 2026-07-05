# Events

Every lifecycle transition dispatches a standard Laravel event, so post-purchase logic (emails, inventory, fulfillment, analytics) lives in listeners rather than inside your handlers.

| Event | Fired when | Payload |
|---|---|---|
| `FastUcp\Events\CheckoutCreated` | After `POST /ucp/checkout-sessions` | `CheckoutResponse $checkout` |
| `FastUcp\Events\CheckoutUpdated` | After `PATCH /ucp/checkout-sessions/{id}` | `CheckoutResponse $checkout` |
| `FastUcp\Events\CheckoutCompleted` | After `POST .../complete` | `Order $order`, `string $checkoutId`, `array $payment` |
| `FastUcp\Events\CheckoutExpired` | Your scheduler decides a session lapsed | `string $checkoutId` |
| `FastUcp\Events\EmbeddedCheckoutReady` | Embedded checkout page served + delegates negotiated | `EmbeddedCheckoutSession $session` |
| `FastUcp\Events\UniversalCartUpdated` | Any cart mutation | `UniversalCart $cart`, `string $action` |

## Listening

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \FastUcp\Events\CheckoutCompleted::class => [
        \App\Listeners\SendOrderConfirmation::class,
        \App\Listeners\DecrementInventory::class,
        \App\Listeners\NotifyFulfillmentCenter::class,
    ],
    \FastUcp\Events\UniversalCartUpdated::class => [
        \App\Listeners\SyncCartAnalytics::class,
    ],
];
```

```php
// app/Listeners/SendOrderConfirmation.php
class SendOrderConfirmation
{
    public function handle(\FastUcp\Events\CheckoutCompleted $event): void
    {
        // $event->order->id, $event->order->lineItems, $event->payment ...
        Mail::to($buyerEmail)->send(new OrderConfirmationMail($event->order));
    }
}
```

Listeners can be queued as usual (`implements ShouldQueue`) — recommended for anything that talks to external services, since these events fire inside the agent-facing request cycle.

> **Note on REST vs other transports:** events fire in the REST controllers. MCP and A2A calls bridge into the same handlers via `UcpManager::callHandler`; if you need events on those paths too, dispatch them inside your handler implementation (the events are plain dispatchable classes).
