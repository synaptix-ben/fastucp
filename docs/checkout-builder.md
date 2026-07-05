# Checkout Builder

`FastUcp\Builders\CheckoutBuilder` assembles a spec-compliant `CheckoutResponse` — line items, totals, fulfillment hierarchy, discounts, messages, status — without hand-writing the nested structure.

## Basic usage

```php
use FastUcp\Builders\CheckoutBuilder;

$checkout = CheckoutBuilder::make('chk_123')          // currency from config('ucp.currency')
    ->addLink('privacy_policy', url('/privacy'))       // links are required by UCP
    ->addLink('terms_of_service', url('/terms'))
    ->addItem('sku_tee', 'Logo T-Shirt', 2500, 2, 'https://cdn.example/tee.png')
    ->addItem('sku_mug', 'Coffee Mug', 1200, 1)
    ->setBuyer(['email' => 'ada@example.com', 'first_name' => 'Ada'])
    ->build();
```

Totals are derived automatically:

```json
"totals": [
    {"type": "subtotal", "amount": 6200},
    {"type": "total", "amount": 6200}
]
```

## Shipping

```php
$builder
    ->addShippingOption('std', 'Standard Shipping', 500, '5-7 days')
    ->addShippingOption('exp', 'Express Shipping', 1500, '1-2 days')
    ->selectShippingOption('std');
```

The builder creates the full `fulfillment.methods[].groups[].options[]` hierarchy, marks the selection, and adds the cost to the totals. Selecting an unknown option id is a no-op.

## Discounts

```php
$builder->addDiscount('SAVE10', 620, '10% off');
```

Adds a `discount` total line and populates `discounts.applied` / `discounts.codes`. The grand total is clamped at zero.

## Validation messages & status

```php
$builder->addError('missing', '$.buyer.email', 'Email required for shipping.');
$builder->addWarning('final_sale', 'This item cannot be returned.');
```

Status is derived automatically:

| Condition | Status |
|---|---|
| No error messages | `ready_for_complete` |
| Any error message | `incomplete` |
| `setContinueUrl(...)` called | `requires_escalation` |
| `setStatus(...)` called | your value wins |

`setBuyer()` adds a `missing` error automatically when the buyer has no email.

## Embedded checkout escalation

```php
$builder->setContinueUrl(route('ucp.embedded-checkout', $sessionId));
// status becomes requires_escalation; the agent will open the URL in an iframe
```

Pass `escalate: false` to set a continue URL without forcing the status (useful for session-recovery links).

## Expiry

```php
$builder->setExpiresAt(now()->addHours(2));   // RFC 3339 in the response
```

UCP defaults to a 6-hour TTL when not set — the cache session store uses the same default.
