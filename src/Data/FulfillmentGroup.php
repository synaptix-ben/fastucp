<?php

namespace FastUcp\Data;

class FulfillmentGroup
{
    /**
     * @param string[] $lineItemIds
     * @param FulfillmentOption[]|null $options
     */
    public function __construct(
        public readonly string $id,
        public readonly array $lineItemIds,
        public readonly ?array $options = null,
        public readonly ?string $selectedOptionId = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'id' => $this->id,
            'line_item_ids' => $this->lineItemIds,
        ];

        if ($this->options !== null) {
            $out['options'] = array_map(
                fn ($o) => $o instanceof FulfillmentOption ? $o->toArray() : $o,
                $this->options
            );
        }
        if ($this->selectedOptionId !== null) {
            $out['selected_option_id'] = $this->selectedOptionId;
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            lineItemIds: $data['line_item_ids'] ?? [],
            options: isset($data['options'])
                ? array_map(fn ($o) => FulfillmentOption::fromArray($o), $data['options'])
                : null,
            selectedOptionId: $data['selected_option_id'] ?? null,
        );
    }
}
