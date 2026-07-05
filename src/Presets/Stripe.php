<?php

namespace FastUcp\Presets;

use FastUcp\Data\PaymentHandler;

/**
 * Pre-configured Stripe payment handler using the platform-tokenizer
 * pattern: the platform (agent surface) collects payment via Stripe and
 * hands the merchant a tokenized credential bound to this checkout.
 */
class Stripe
{
    public static function make(
        string $publishableKey,
        string $merchantAccountId,
        string $environment = 'test',
        array $paymentMethodTypes = ['card'],
        string $version = '2026-01-11',
    ): PaymentHandler {
        return new PaymentHandler(
            id: 'stripe',
            name: 'com.stripe.pay',
            version: $version,
            spec: 'https://docs.stripe.com/payments/ucp',
            configSchema: 'https://docs.stripe.com/payments/ucp/schemas/config.json',
            instrumentSchemas: [
                'https://docs.stripe.com/payments/ucp/schemas/card_payment_instrument.json',
            ],
            config: [
                'environment' => $environment,
                'publishable_key' => $publishableKey,
                'merchant_account_id' => $merchantAccountId,
                'payment_method_types' => $paymentMethodTypes,
                'tokenization' => [
                    'type' => 'platform_tokenizer',
                    'token_type' => 'payment_method',
                ],
            ],
        );
    }
}
