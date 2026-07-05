<?php

namespace FastUcp\Tests\Unit;

use FastUcp\Builders\CheckoutBuilder;
use FastUcp\Tests\TestCase;
use FastUcp\UcpManager;

class CheckoutBuilderTest extends TestCase
{
    protected function builder(string $sessionId = 'chk_test'): CheckoutBuilder
    {
        return new CheckoutBuilder($this->app->make(UcpManager::class), $sessionId);
    }

    public function test_totals_are_auto_calculated(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 3)
            ->addItem('sku_2', 'Gadget', 500, 1)
            ->build();

        $totals = collect($checkout->totals)->keyBy(fn ($t) => $t->type);
        $this->assertSame(3500, $totals['subtotal']->amount);
        $this->assertSame(3500, $totals['total']->amount);
    }

    public function test_shipping_and_discount_affect_total(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 10000, 1)
            ->addShippingOption('std', 'Standard', 500)
            ->selectShippingOption('std')
            ->addDiscount('SAVE10', 1000, '10% off')
            ->build();

        $totals = collect($checkout->totals)->keyBy(fn ($t) => $t->type);
        $this->assertSame(500, $totals['fulfillment']->amount);
        $this->assertSame(1000, $totals['discount']->amount);
        $this->assertSame(9500, $totals['total']->amount);

        // Fulfillment hierarchy is assembled automatically.
        $group = $checkout->fulfillment->methods[0]->groups[0];
        $this->assertSame('std', $group->selectedOptionId);
        $this->assertSame(['li_1'], $group->lineItemIds);

        // Discounts object mirrors applied codes.
        $this->assertSame(['SAVE10'], $checkout->discounts->codes);
    }

    public function test_total_never_goes_negative(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Cheap', 100, 1)
            ->addDiscount('HUGE', 5000, 'Overkill discount')
            ->build();

        $totals = collect($checkout->totals)->keyBy(fn ($t) => $t->type);
        $this->assertSame(0, $totals['total']->amount);
    }

    public function test_selecting_unknown_shipping_option_is_ignored(): void
    {
        // Regression: the Python port accessed an uninitialized attribute here.
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->addShippingOption('std', 'Standard', 500)
            ->selectShippingOption('does_not_exist')
            ->build();

        $this->assertNull($checkout->fulfillment->methods[0]->groups[0]->selectedOptionId);
        $totals = collect($checkout->totals)->keyBy(fn ($t) => $t->type);
        $this->assertSame(1000, $totals['total']->amount);
    }

    public function test_errors_flip_status_to_incomplete(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->addError('missing', '$.buyer.email', 'Email required')
            ->build();

        $this->assertSame('incomplete', $checkout->status);
        $this->assertSame('missing', $checkout->messages[0]->code);
    }

    public function test_warnings_do_not_block_completion(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->addWarning('final_sale', 'This item cannot be returned')
            ->build();

        $this->assertSame('ready_for_complete', $checkout->status);
    }

    public function test_buyer_without_email_adds_error(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->setBuyer(['first_name' => 'Ada'])
            ->build();

        $this->assertSame('incomplete', $checkout->status);
    }

    public function test_currency_defaults_from_config(): void
    {
        config()->set('ucp.currency', 'EUR');

        $checkout = CheckoutBuilder::make('chk_eur')
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->build();

        $this->assertSame('EUR', $checkout->currency);
    }

    public function test_continue_url_escalates_status(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->setContinueUrl('https://merchant.test/ucp/embedded-checkout/chk_test')
            ->build();

        $this->assertSame('requires_escalation', $checkout->status);
    }

    public function test_ucp_context_carries_active_capabilities(): void
    {
        $checkout = $this->builder()
            ->addItem('sku_1', 'Widget', 1000, 1)
            ->build();

        $names = array_column($checkout->ucp['capabilities'], 'name');
        $this->assertContains('dev.ucp.shopping.checkout', $names);
    }
}
