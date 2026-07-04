<?php

namespace FastUcp\Data;

class CardPaymentInstrument extends PaymentInstrument
{
    public function __construct(
        string $id,
        string $handlerId,
        public readonly string $brand,
        public readonly string $lastDigits,
        public readonly ?int $expiryMonth = null,
        public readonly ?int $expiryYear = null,
        public readonly ?string $richTextDescription = null,
        public readonly ?string $richCardArt = null,
        ?PostalAddress $billingAddress = null,
        ?array $credential = null,
    ) {
        parent::__construct($id, $handlerId, 'card', $billingAddress, $credential);
    }

    public function toArray(): array
    {
        return parent::toArray() + array_filter([
            'brand' => $this->brand,
            'last_digits' => $this->lastDigits,
            'expiry_month' => $this->expiryMonth,
            'expiry_year' => $this->expiryYear,
            'rich_text_description' => $this->richTextDescription,
            'rich_card_art' => $this->richCardArt,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            handlerId: $data['handler_id'],
            brand: $data['brand'] ?? '',
            lastDigits: $data['last_digits'] ?? '',
            expiryMonth: $data['expiry_month'] ?? null,
            expiryYear: $data['expiry_year'] ?? null,
            richTextDescription: $data['rich_text_description'] ?? null,
            richCardArt: $data['rich_card_art'] ?? null,
            billingAddress: isset($data['billing_address']) ? PostalAddress::fromArray($data['billing_address']) : null,
            credential: $data['credential'] ?? null,
        );
    }
}
