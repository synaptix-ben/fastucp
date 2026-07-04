<?php

namespace FastUcp\Events;

use FastUcp\Data\CheckoutResponse;
use Illuminate\Foundation\Events\Dispatchable;

class CheckoutUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly CheckoutResponse $checkout,
    ) {}
}
