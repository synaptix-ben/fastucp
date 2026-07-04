<?php

namespace FastUcp\Data;

class Item
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly int $price,
        public readonly ?string $imageUrl = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'price' => $this->price,
            'image_url' => $this->imageUrl,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            title: $data['title'] ?? '',
            price: $data['price'] ?? 0,
            imageUrl: $data['image_url'] ?? null,
        );
    }
}
