<?php

namespace FastUcp\Data;

class Order
{
    /**
     * @param array $ucp UCP context (version + active capabilities)
     * @param OrderLineItem[] $lineItems
     * @param array{expectations?: array, events?: array} $fulfillment
     * @param Total[] $totals
     * @param array[]|null $adjustments
     */
    public function __construct(
        public readonly array $ucp,
        public readonly string $id,
        public readonly string $checkoutId,
        public readonly string $permalinkUrl,
        public readonly array $lineItems,
        public readonly array $fulfillment,
        public readonly array $totals,
        public readonly ?array $adjustments = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'ucp' => $this->ucp,
            'id' => $this->id,
            'checkout_id' => $this->checkoutId,
            'permalink_url' => $this->permalinkUrl,
            'line_items' => array_map(
                fn ($li) => $li instanceof OrderLineItem ? $li->toArray() : $li,
                $this->lineItems
            ),
            'fulfillment' => $this->fulfillment,
            'totals' => array_map(
                fn ($t) => $t instanceof Total ? $t->toArray() : $t,
                $this->totals
            ),
        ];

        if ($this->adjustments !== null) {
            $out['adjustments'] = $this->adjustments;
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ucp: $data['ucp'] ?? [],
            id: $data['id'],
            checkoutId: $data['checkout_id'],
            permalinkUrl: $data['permalink_url'],
            lineItems: array_map(fn ($li) => OrderLineItem::fromArray($li), $data['line_items'] ?? []),
            fulfillment: $data['fulfillment'] ?? ['expectations' => [], 'events' => []],
            totals: array_map(fn ($t) => Total::fromArray($t), $data['totals'] ?? []),
            adjustments: $data['adjustments'] ?? null,
        );
    }
}
