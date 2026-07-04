<?php

namespace FastUcp\Data;

class FulfillmentMethod
{
    /**
     * @param string[] $lineItemIds
     * @param FulfillmentGroup[]|null $groups
     * @param array[]|null $destinations
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly array $lineItemIds,
        public readonly ?array $groups = null,
        public readonly ?array $destinations = null,
        public readonly ?string $selectedDestinationId = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'id' => $this->id,
            'type' => $this->type,
            'line_item_ids' => $this->lineItemIds,
        ];

        if ($this->destinations !== null) {
            $out['destinations'] = $this->destinations;
        }
        if ($this->selectedDestinationId !== null) {
            $out['selected_destination_id'] = $this->selectedDestinationId;
        }
        if ($this->groups !== null) {
            $out['groups'] = array_map(
                fn ($g) => $g instanceof FulfillmentGroup ? $g->toArray() : $g,
                $this->groups
            );
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            type: $data['type'],
            lineItemIds: $data['line_item_ids'] ?? [],
            groups: isset($data['groups'])
                ? array_map(fn ($g) => FulfillmentGroup::fromArray($g), $data['groups'])
                : null,
            destinations: $data['destinations'] ?? null,
            selectedDestinationId: $data['selected_destination_id'] ?? null,
        );
    }
}
