<?php

namespace FastUcp\Data;

class AppliedDiscount
{
    public function __construct(
        public readonly string $title,
        public readonly int $amount,
        public readonly ?string $code = null,
        public readonly bool $automatic = false,
        public readonly ?string $method = null,
        public readonly ?int $priority = null,
        public readonly ?array $allocations = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'title' => $this->title,
            'amount' => $this->amount,
        ];

        if ($this->code !== null) {
            $out['code'] = $this->code;
        }
        if ($this->automatic) {
            $out['automatic'] = true;
        }
        if ($this->method !== null) {
            $out['method'] = $this->method;
        }
        if ($this->priority !== null) {
            $out['priority'] = $this->priority;
        }
        if ($this->allocations !== null) {
            $out['allocations'] = $this->allocations;
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            amount: $data['amount'],
            code: $data['code'] ?? null,
            automatic: $data['automatic'] ?? false,
            method: $data['method'] ?? null,
            priority: $data['priority'] ?? null,
            allocations: $data['allocations'] ?? null,
        );
    }
}
