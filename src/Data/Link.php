<?php

namespace FastUcp\Data;

class Link
{
    public const TYPES = ['privacy_policy', 'terms_of_service', 'refund_policy', 'shipping_policy', 'faq'];

    public function __construct(
        public readonly string $type,
        public readonly string $url,
        public readonly ?string $title = null,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'url' => $this->url,
            'title' => $this->title,
        ], fn ($v) => $v !== null);
    }

    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            url: $data['url'],
            title: $data['title'] ?? null,
        );
    }
}
