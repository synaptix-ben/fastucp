<?php

namespace FastUcp\Data;

class FulfillmentResponse
{
    /**
     * @param FulfillmentMethod[]|null $methods
     * @param array[]|null $availableMethods
     */
    public function __construct(
        public readonly ?array $methods = null,
        public readonly ?array $availableMethods = null,
    ) {}

    public function toArray(): array
    {
        $out = [];

        if ($this->methods !== null) {
            $out['methods'] = array_map(
                fn ($m) => $m instanceof FulfillmentMethod ? $m->toArray() : $m,
                $this->methods
            );
        }
        if ($this->availableMethods !== null) {
            $out['available_methods'] = $this->availableMethods;
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            methods: isset($data['methods'])
                ? array_map(fn ($m) => FulfillmentMethod::fromArray($m), $data['methods'])
                : null,
            availableMethods: $data['available_methods'] ?? null,
        );
    }
}
