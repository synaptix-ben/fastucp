<?php

namespace FastUcp\Tests\Support;

use FastUcp\Contracts\DiscoveryHandler;

class FakeDiscoveryHandler implements DiscoveryHandler
{
    public function search(string $query): array
    {
        $items = [];

        foreach (FakeCheckoutHandler::PRODUCTS as $sku => $product) {
            if ($query === '' || stripos($product['title'], $query) !== false) {
                $items[] = [
                    'id' => $sku,
                    'title' => $product['title'],
                    'price' => $product['price'],
                    'image_url' => $product['image'],
                ];
            }
        }

        return ['items' => $items];
    }
}
