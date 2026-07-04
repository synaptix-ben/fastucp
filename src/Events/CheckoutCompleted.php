<?php

namespace FastUcp\Events;

use FastUcp\Data\Order;
use Illuminate\Foundation\Events\Dispatchable;

class CheckoutCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly Order $order,
        public readonly string $checkoutId,
        public readonly array $payment = [],
    ) {}
}
