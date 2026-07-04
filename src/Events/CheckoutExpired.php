<?php

namespace FastUcp\Events;

use Illuminate\Foundation\Events\Dispatchable;

class CheckoutExpired
{
    use Dispatchable;

    public function __construct(
        public readonly string $checkoutId,
    ) {}
}
