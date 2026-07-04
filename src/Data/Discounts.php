<?php

namespace FastUcp\Data;

class Discounts
{
    /**
     * @param string[]|null $codes
     * @param AppliedDiscount[]|null $applied
     */
    public function __construct(
        public readonly ?array $codes = null,
        public readonly ?array $applied = null,
    ) {}

    public function toArray(): array
    {
        $out = [];

        if ($this->codes !== null) {
            $out['codes'] = $this->codes;
        }
        if ($this->applied !== null) {
            $out['applied'] = array_map(
                fn ($d) => $d instanceof AppliedDiscount ? $d->toArray() : $d,
                $this->applied
            );
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            codes: $data['codes'] ?? null,
            applied: isset($data['applied'])
                ? array_map(fn ($d) => AppliedDiscount::fromArray($d), $data['applied'])
                : null,
        );
    }
}
