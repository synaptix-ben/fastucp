<?php

namespace FastUcp\Contracts;

interface DiscoveryHandler
{
    /**
     * Search products/items.
     *
     * @return array{items: array<int, array>}
     */
    public function search(string $query): array;
}
