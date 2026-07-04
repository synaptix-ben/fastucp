<?php

namespace FastUcp\Data;

class Total
{
    public const TYPES = ['items_discount', 'subtotal', 'discount', 'fulfillment', 'tax', 'fee', 'total'];

    public function __construct(
        public readonly string $type,
        public readonly int $amount,
        public readonly ?string $displayText = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'amount' => $this->amount,
            'display_text' => $this->displayText,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            amount: $data['amount'],
            displayText: $data['display_text'] ?? null,
        );
    }
}
