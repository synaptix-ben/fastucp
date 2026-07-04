<?php

namespace FastUcp\Events;

use FastUcp\Data\CheckoutResponse;
use Illuminate\Foundation\Events\Dispatchable;

class CheckoutCreated
{
    use Dispatchable;

    public function __construct(
        public readonly CheckoutResponse $checkout,
    ) {}
}
