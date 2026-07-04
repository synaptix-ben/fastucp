<?php

namespace FastUcp\Data;

class PaymentInstrument
{
    public function __construct(
        public readonly string $id,
        public readonly string $handlerId,
        public readonly string $type,
        public readonly ?PostalAddress $billingAddress = null,
        public readonly ?array $credential = null,
        public readonly array $extra = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'handler_id' => $this->handlerId,
            'type' => $this->type,
            'billing_address' => $this->billingAddress?->toArray(),
            'credential' => $this->credential,
        ], fn ($v) => $v !== null) + $this->extra;
    }

    public static function fromArray(array $data): self
    {
        if (($data['type'] ?? null) === 'card') {
            return CardPaymentInstrument::fromArray($data);
        }

        $known = ['id', 'handler_id', 'type', 'billing_address', 'credential'];

        return new self(
            id: $data['id'],
            handlerId: $data['handler_id'],
            type: $data['type'],
            billingAddress: isset($data['billing_address']) ? PostalAddress::fromArray($data['billing_address']) : null,
            credential: $data['credential'] ?? null,
            extra: array_diff_key($data, array_flip($known)),
        );
    }
}
