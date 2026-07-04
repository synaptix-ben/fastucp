<?php

namespace FastUcp\Data;

class OrderLineItem
{
    /**
     * @param array{total: int, fulfilled: int} $quantity
     * @param Total[] $totals
     */
    public function __construct(
        public readonly string $id,
        public readonly Item $item,
        public readonly array $quantity,
        public readonly array $totals,
        public readonly string $status = 'processing',
        public readonly ?string $parentId = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'item' => $this->item->toArray(),
            'quantity' => $this->quantity,
            'totals' => array_map(fn ($t) => $t instanceof Total ? $t->toArray() : $t, $this->totals),
            'status' => $this->status,
            'parent_id' => $this->parentId,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            item: Item::fromArray($data['item']),
            quantity: $data['quantity'],
            totals: array_map(fn ($t) => Total::fromArray($t), $data['totals'] ?? []),
            status: $data['status'] ?? 'processing',
            parentId: $data['parent_id'] ?? null,
        );
    }
}
