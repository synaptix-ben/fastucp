<?php

namespace FastUcp\Events;

use FastUcp\Data\EmbeddedCheckoutSession;
use Illuminate\Foundation\Events\Dispatchable;

class EmbeddedCheckoutReady
{
    use Dispatchable;

    public function __construct(
        public readonly EmbeddedCheckoutSession $session,
    ) {}
}
