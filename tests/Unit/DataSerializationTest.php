<?php

namespace FastUcp\Tests\Unit;

use FastUcp\Data\Buyer;
use FastUcp\Data\CheckoutResponse;
use FastUcp\Data\EmbeddedCheckoutSession;
use FastUcp\Data\Message;
use FastUcp\Data\Order;
use FastUcp\Data\PaymentInstrument;
use FastUcp\Data\UniversalCart;
use FastUcp\Data\UniversalCartItem;
use PHPUnit\Framework\TestCase;

class DataSerializationTest extends TestCase
{
    public function test_checkout_response_round_trips(): void
    {
        $data = [
            'ucp' => ['version' => '2026-01-11', 'capabilities' => [['name' => 'dev.ucp.shopping.checkout', 'version' => '2026-01-11']]],
            'id' => 'chk_1',
            'line_items' => [
                [
                    'id' => 'li_1',
                    'item' => ['id' => 'sku_1', 'title' => 'Widget', 'price' => 1000, 'image_url' => 'https://img.test/w.png'],
                    'quantity' => 2,
                    'totals' => [['type' => 'subtotal', 'amount' => 2000], ['type' => 'total', 'amount' => 2000]],
                ],
            ],
            'status' => 'ready_for_complete',
            'currency' => 'USD',
            'totals' => [['type' => 'subtotal', 'amount' => 2000], ['type' => 'total', 'amount' => 2000]],
            'links' => [['type' => 'privacy_policy', 'url' => 'https://m.test/privacy']],
            'payment' => ['handlers' => []],
            'buyer' => ['email' => 'a@b.c'],
            'continue_url' => 'https://m.test/continue',
        ];

        $roundTripped = CheckoutResponse::fromArray($data)->toArray();

        $this->assertSame($data['id'], $roundTripped['id']);
        $this->assertSame($data['line_items'], $roundTripped['line_items']);
        $this->assertSame($data['totals'], $roundTripped['totals']);
        $this->assertSame($data['buyer'], $roundTripped['buyer']);
        $this->assertSame($data['continue_url'], $roundTripped['continue_url']);
    }

    public function test_order_round_trips(): void
    {
        $data = [
            'ucp' => ['version' => '2026-01-11', 'capabilities' => []],
            'id' => 'ord_1',
            'checkout_id' => 'chk_1',
            'permalink_url' => 'https://m.test/orders/ord_1',
            'line_items' => [
                [
                    'id' => 'li_1',
                    'item' => ['id' => 'sku_1', 'title' => 'Widget', 'price' => 1000],
                    'quantity' => ['total' => 1, 'fulfilled' => 0],
                    'totals' => [['type' => 'total', 'amount' => 1000]],
                    'status' => 'processing',
                ],
            ],
            'fulfillment' => ['expectations' => [], 'events' => []],
            'totals' => [['type' => 'total', 'amount' => 1000]],
        ];

        $this->assertSame($data, Order::fromArray($data)->toArray());
    }

    public function test_message_discriminated_union(): void
    {
        $error = Message::fromArray(['type' => 'error', 'code' => 'missing', 'content' => 'x', 'severity' => 'recoverable']);
        $this->assertTrue($error->isError());
        $this->assertSame('recoverable', $error->toArray()['severity']);

        $info = Message::info('FYI');
        $this->assertFalse($info->isError());
        $this->assertArrayNotHasKey('severity', $info->toArray());
    }

    public function test_card_instrument_is_detected_by_type(): void
    {
        $instrument = PaymentInstrument::fromArray([
            'id' => 'pi_1',
            'handler_id' => 'gpay',
            'type' => 'card',
            'brand' => 'visa',
            'last_digits' => '4242',
        ]);

        $this->assertInstanceOf(\FastUcp\Data\CardPaymentInstrument::class, $instrument);
        $this->assertSame('visa', $instrument->toArray()['brand']);
    }

    public function test_buyer_null_fields_are_omitted(): void
    {
        $buyer = new Buyer(email: 'a@b.c');
        $this->assertSame(['email' => 'a@b.c'], $buyer->toArray());
    }

    public function test_universal_cart_grouping_and_totals(): void
    {
        $cart = new UniversalCart('cart_1', [
            new UniversalCartItem('1', 'cart_1', null, 'sku_a', 'A', 1000, 2),
            new UniversalCartItem('2', 'cart_1', 'https://other.test', 'sku_b', 'B', 500, 1, merchantName: 'Other'),
        ]);

        $this->assertFalse($cart->isSingleMerchant());
        $this->assertSame(2500, $cart->total());

        $array = $cart->toArray();
        $this->assertCount(2, $array['merchants']);
        $this->assertNull($array['merchants'][0]['merchant_url']);
        $this->assertSame(2000, $array['merchants'][0]['subtotal']);
        $this->assertSame('Other', $array['merchants'][1]['merchant_name']);
    }

    public function test_single_merchant_cart(): void
    {
        $cart = new UniversalCart('cart_2', [
            new UniversalCartItem('1', 'cart_2', null, 'sku_a', 'A', 1000, 1),
            new UniversalCartItem('2', 'cart_2', null, 'sku_b', 'B', 500, 1),
        ]);

        $this->assertTrue($cart->isSingleMerchant());
    }

    public function test_ec_delegate_negotiation(): void
    {
        $session = EmbeddedCheckoutSession::negotiate(
            'chk_1',
            'payment.credential, fulfillment.address_change ,bogus.delegate',
            'https://host.example',
        );

        $this->assertSame(
            ['payment.credential', 'fulfillment.address_change'],
            $session->acceptedDelegates
        );
        $this->assertCount(3, $session->requestedDelegates);
    }

    public function test_ec_delegate_handles_empty_param(): void
    {
        $session = EmbeddedCheckoutSession::negotiate('chk_1', null);

        $this->assertSame([], $session->requestedDelegates);
        $this->assertSame([], $session->acceptedDelegates);
    }
}
