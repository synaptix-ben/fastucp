<?php

namespace FastUcp\Data;

class FulfillmentOption
{
    /**
     * @param Total[] $totals
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly array $totals = [],
        public readonly ?string $description = null,
        public readonly ?string $carrier = null,
        public readonly ?string $earliestFulfillmentTime = null,
        public readonly ?string $latestFulfillmentTime = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description !== '' ? $this->description : null,
            'carrier' => $this->carrier,
            'earliest_fulfillment_time' => $this->earliestFulfillmentTime,
            'latest_fulfillment_time' => $this->latestFulfillmentTime,
            'totals' => array_map(fn ($t) => $t instanceof Total ? $t->toArray() : $t, $this->totals),
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'],
            totals: array_map(fn ($t) => Total::fromArray($t), $data['totals'] ?? []),
            description: $data['description'] ?? null,
            carrier: $data['carrier'] ?? null,
            earliestFulfillmentTime: $data['earliest_fulfillment_time'] ?? null,
            latestFulfillmentTime: $data['latest_fulfillment_time'] ?? null,
        );
    }
}
