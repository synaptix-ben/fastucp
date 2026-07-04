<?php

namespace FastUcp\Events;

use FastUcp\Data\UniversalCart;
use Illuminate\Foundation\Events\Dispatchable;

class UniversalCartUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly UniversalCart $cart,
        public readonly string $action, // 'added' | 'updated' | 'removed' | 'checked_out'
    ) {}
}
