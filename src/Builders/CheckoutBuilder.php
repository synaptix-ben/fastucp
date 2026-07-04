<?php

namespace FastUcp\Builders;

use FastUcp\Data\AppliedDiscount;
use FastUcp\Data\Buyer;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\Discounts;
use FastUcp\Data\FulfillmentGroup;
use FastUcp\Data\FulfillmentMethod;
use FastUcp\Data\FulfillmentOption;
use FastUcp\Data\FulfillmentResponse;
use FastUcp\Data\Item;
use FastUcp\Data\LineItem;
use FastUcp\Data\Link;
use FastUcp\Data\Message;
use FastUcp\Data\PaymentResponse;
use FastUcp\Data\Total;
use FastUcp\UcpManager;

/**
 * Fluent builder for constructing a valid UCP CheckoutResponse without
 * hand-assembling the nested structure. Totals, fulfillment hierarchy,
 * and status are derived automatically.
 */
class CheckoutBuilder
{
    /** @var LineItem[] */
    protected array $lineItems = [];

    /** @var Message[] */
    protected array $messages = [];

    /** @var Link[] */
    protected array $links = [];

    /** @var FulfillmentOption[] */
    protected array $shippingOptions = [];

    /** @var AppliedDiscount[] */
    protected array $discounts = [];

    protected ?Buyer $buyer = null;

    protected int $subtotal = 0;

    protected int $shippingCost = 0;

    protected int $discountAmount = 0;

    protected ?string $selectedShippingId = null;

    protected ?string $continueUrl = null;

    protected ?string $forcedStatus = null;

    protected ?string $expiresAt = null;

    public function __construct(
        protected UcpManager $manager,
        protected string $sessionId,
        protected ?string $currency = null,
    ) {
        $this->currency ??= config('ucp.currency', 'USD');
    }

    public static function make(string $sessionId, ?string $currency = null): self
    {
        return new self(app(UcpManager::class), $sessionId, $currency);
    }

    public function addItem(
        string $itemId,
        string $title,
        int $price,
        int $quantity,
        ?string $imgUrl = null,
    ): self {
        $lineTotal = $price * $quantity;
        $this->subtotal += $lineTotal;

        $this->lineItems[] = new LineItem(
            id: 'li_'.(count($this->lineItems) + 1),
            item: new Item(id: $itemId, title: $title, price: $price, imageUrl: $imgUrl),
            quantity: $quantity,
            totals: [
                new Total('subtotal', $lineTotal),
                new Total('total', $lineTotal),
            ],
        );

        return $this;
    }

    public function setBuyer(Buyer|array|null $buyerData): self
    {
        if ($buyerData === null) {
            return $this;
        }

        $this->buyer = $buyerData instanceof Buyer ? $buyerData : Buyer::fromArray($buyerData);

        if ($this->buyer->email === null) {
            $this->addError('missing', '$.buyer.email', 'Email address is required to checkout.');
        }

        return $this;
    }

    public function addError(string $code, string $path, string $message): self
    {
        $this->messages[] = Message::error($code, $message, $path);

        return $this;
    }

    public function addWarning(string $code, string $message, ?string $path = null): self
    {
        $this->messages[] = Message::warning($code, $message, $path);

        return $this;
    }

    public function addLink(string $type, string $url, ?string $title = null): self
    {
        $this->links[] = new Link($type, $url, $title);

        return $this;
    }

    public function addShippingOption(string $id, string $title, int $amount, string $description = ''): self
    {
        $this->shippingOptions[] = new FulfillmentOption(
            id: $id,
            title: $title,
            totals: [new Total('fulfillment', $amount)],
            description: $description !== '' ? $description : null,
        );

        return $this;
    }

    public function selectShippingOption(string $optionId): self
    {
        foreach ($this->shippingOptions as $option) {
            if ($option->id === $optionId) {
                $this->shippingCost = $option->totals[0]->amount;
                $this->selectedShippingId = $optionId;

                return $this;
            }
        }

        return $this;
    }

    public function addDiscount(string $code, int $amount, string $title): self
    {
        $this->discounts[] = new AppliedDiscount(title: $title, amount: $amount, code: $code);
        $this->discountAmount += $amount;

        return $this;
    }

    /**
     * Escalate this checkout to the Embedded Checkout Protocol: sets
     * continue_url and forces requires_escalation status.
     */
    public function setContinueUrl(string $url, bool $escalate = true): self
    {
        $this->continueUrl = $url;

        if ($escalate) {
            $this->forcedStatus = 'requires_escalation';
        }

        return $this;
    }

    public function setStatus(string $status): self
    {
        $this->forcedStatus = $status;

        return $this;
    }

    public function setExpiresAt(\DateTimeInterface|string $expiresAt): self
    {
        $this->expiresAt = $expiresAt instanceof \DateTimeInterface
            ? $expiresAt->format(DATE_RFC3339)
            : $expiresAt;

        return $this;
    }

    public function build(): CheckoutResponse
    {
        $finalTotal = max(0, $this->subtotal + $this->shippingCost - $this->discountAmount);

        $totals = [new Total('subtotal', $this->subtotal)];

        if ($this->shippingCost > 0) {
            $totals[] = new Total('fulfillment', $this->shippingCost);
        }
        if ($this->discountAmount > 0) {
            $totals[] = new Total('discount', $this->discountAmount);
        }

        $totals[] = new Total('total', $finalTotal);

        $fulfillment = null;
        if ($this->shippingOptions !== []) {
            $lineItemIds = array_map(fn (LineItem $li) => $li->id, $this->lineItems);

            $fulfillment = new FulfillmentResponse(methods: [
                new FulfillmentMethod(
                    id: 'method_shipping',
                    type: 'shipping',
                    lineItemIds: $lineItemIds,
                    groups: [
                        new FulfillmentGroup(
                            id: 'group_default',
                            lineItemIds: $lineItemIds,
                            options: $this->shippingOptions,
                            selectedOptionId: $this->selectedShippingId,
                        ),
                    ],
                ),
            ]);
        }

        $discounts = null;
        if ($this->discounts !== []) {
            $discounts = new Discounts(
                codes: array_values(array_filter(array_map(fn ($d) => $d->code, $this->discounts))),
                applied: $this->discounts,
            );
        }

        $hasErrors = array_reduce(
            $this->messages,
            fn (bool $carry, Message $m) => $carry || $m->isError(),
            false
        );

        $status = $this->forcedStatus
            ?? ($hasErrors ? 'incomplete' : 'ready_for_complete');

        return new CheckoutResponse(
            ucp: $this->manager->createUcpContext(),
            id: $this->sessionId,
            lineItems: $this->lineItems,
            status: $status,
            currency: $this->currency,
            totals: $totals,
            payment: new PaymentResponse(handlers: $this->manager->paymentHandlers()),
            links: $this->links,
            buyer: $this->buyer,
            messages: $this->messages !== [] ? $this->messages : null,
            expiresAt: $this->expiresAt,
            continueUrl: $this->continueUrl,
            fulfillment: $fulfillment,
            discounts: $discounts,
        );
    }
}
