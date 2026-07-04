<?php

namespace FastUcp\Contracts;

use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Order;

interface CheckoutHandler
{
    public function createCheckout(array $payload): CheckoutResponse;

    public function updateCheckout(string $id, array $payload): CheckoutResponse;

    public function completeCheckout(string $id, array $payment): Order;
}
