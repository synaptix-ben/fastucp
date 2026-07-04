<?php

namespace FastUcp\Data;

class UniversalCartItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $cartId,
        public readonly ?string $merchantUrl,
        public readonly string $itemId,
        public readonly string $title,
        public readonly int $price,
        public readonly int $quantity,
        public readonly ?string $imageUrl = null,
        public readonly string $currency = 'USD',
        public readonly ?string $merchantName = null,
        public readonly ?array $metadata = null,
    ) {}

    public function lineTotal(): int
    {
        return $this->price * $this->quantity;
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'cart_id' => $this->cartId,
            'merchant_url' => $this->merchantUrl,
            'merchant_name' => $this->merchantName,
            'item_id' => $this->itemId,
            'title' => $this->title,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'image_url' => $this->imageUrl,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
            'line_total' => $this->lineTotal(),
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            cartId: $data['cart_id'],
            merchantUrl: $data['merchant_url'] ?? null,
            itemId: $data['item_id'],
            title: $data['title'],
            price: (int) $data['price'],
            quantity: (int) $data['quantity'],
            imageUrl: $data['image_url'] ?? null,
            currency: $data['currency'] ?? 'USD',
            merchantName: $data['merchant_name'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }
}
