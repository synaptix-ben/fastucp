<?php

namespace FastUcp\Data;

class CheckoutResponse
{
    public const STATUSES = [
        'incomplete',
        'requires_escalation',
        'ready_for_complete',
        'complete_in_progress',
        'completed',
        'canceled',
    ];

    /**
     * @param array $ucp UCP context (version + active capabilities)
     * @param LineItem[] $lineItems
     * @param Total[] $totals
     * @param Link[] $links
     * @param Message[]|null $messages
     */
    public function __construct(
        public readonly array $ucp,
        public readonly string $id,
        public readonly array $lineItems,
        public readonly string $status,
        public readonly string $currency,
        public readonly array $totals,
        public readonly PaymentResponse $payment,
        public readonly array $links = [],
        public readonly ?Buyer $buyer = null,
        public readonly ?array $messages = null,
        public readonly ?string $expiresAt = null,
        public readonly ?string $continueUrl = null,
        public readonly ?array $order = null,
        public readonly ?FulfillmentResponse $fulfillment = null,
        public readonly ?Discounts $discounts = null,
    ) {}

    public function toArray(): array
    {
        $out = [
            'ucp' => $this->ucp,
            'id' => $this->id,
            'line_items' => array_map(
                fn ($li) => $li instanceof LineItem ? $li->toArray() : $li,
                $this->lineItems
            ),
            'status' => $this->status,
            'currency' => $this->currency,
            'totals' => array_map(
                fn ($t) => $t instanceof Total ? $t->toArray() : $t,
                $this->totals
            ),
            'links' => array_map(
                fn ($l) => $l instanceof Link ? $l->toArray() : $l,
                $this->links
            ),
            'payment' => $this->payment->toArray(),
        ];

        if ($this->buyer !== null) {
            $out['buyer'] = $this->buyer->toArray();
        }
        if ($this->messages !== null && $this->messages !== []) {
            $out['messages'] = array_map(
                fn ($m) => $m instanceof Message ? $m->toArray() : $m,
                $this->messages
            );
        }
        if ($this->expiresAt !== null) {
            $out['expires_at'] = $this->expiresAt;
        }
        if ($this->continueUrl !== null) {
            $out['continue_url'] = $this->continueUrl;
        }
        if ($this->order !== null) {
            $out['order'] = $this->order;
        }
        if ($this->fulfillment !== null) {
            $fulfillment = $this->fulfillment->toArray();
            if ($fulfillment !== []) {
                $out['fulfillment'] = $fulfillment;
            }
        }
        if ($this->discounts !== null) {
            $discounts = $this->discounts->toArray();
            if ($discounts !== []) {
                $out['discounts'] = $discounts;
            }
        }

        return $out;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            ucp: $data['ucp'] ?? [],
            id: $data['id'],
            lineItems: array_map(fn ($li) => LineItem::fromArray($li), $data['line_items'] ?? []),
            status: $data['status'],
            currency: $data['currency'],
            totals: array_map(fn ($t) => Total::fromArray($t), $data['totals'] ?? []),
            payment: PaymentResponse::fromArray($data['payment'] ?? []),
            links: array_map(fn ($l) => Link::fromArray($l), $data['links'] ?? []),
            buyer: isset($data['buyer']) ? Buyer::fromArray($data['buyer']) : null,
            messages: isset($data['messages'])
                ? array_map(fn ($m) => Message::fromArray($m), $data['messages'])
                : null,
            expiresAt: $data['expires_at'] ?? null,
            continueUrl: $data['continue_url'] ?? null,
            order: $data['order'] ?? null,
            fulfillment: isset($data['fulfillment']) ? FulfillmentResponse::fromArray($data['fulfillment']) : null,
            discounts: isset($data['discounts']) ? Discounts::fromArray($data['discounts']) : null,
        );
    }
}
