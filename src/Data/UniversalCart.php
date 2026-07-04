<?php

namespace FastUcp\Data;

class UniversalCart
{
    /**
     * @param UniversalCartItem[] $items
     */
    public function __construct(
        public readonly string $cartId,
        public readonly array $items = [],
    ) {}

    /**
     * Group items by merchant URL. Items with a null merchant URL are
     * grouped under the 'self' key (local merchant).
     *
     * @return array<string, UniversalCartItem[]>
     */
    public function groupedByMerchant(): array
    {
        $groups = [];
        foreach ($this->items as $item) {
            $key = $item->merchantUrl ?? 'self';
            $groups[$key][] = $item;
        }

        return $groups;
    }

    public function isSingleMerchant(): bool
    {
        return count($this->groupedByMerchant()) <= 1;
    }

    public function total(): int
    {
        return array_sum(array_map(fn ($i) => $i->lineTotal(), $this->items));
    }

    public function toArray(): array
    {
        $merchants = [];
        foreach ($this->groupedByMerchant() as $merchantUrl => $items) {
            $merchants[] = [
                'merchant_url' => $merchantUrl === 'self' ? null : $merchantUrl,
                'merchant_name' => $items[0]->merchantName,
                'items' => array_map(fn ($i) => $i->toArray(), $items),
                'subtotal' => array_sum(array_map(fn ($i) => $i->lineTotal(), $items)),
                'currency' => $items[0]->currency,
            ];
        }

        return [
            'cart_id' => $this->cartId,
            'merchants' => $merchants,
            'item_count' => array_sum(array_map(fn ($i) => $i->quantity, $this->items)),
            'total' => $this->total(),
            'single_merchant' => $this->isSingleMerchant(),
        ];
    }
}
