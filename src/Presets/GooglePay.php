<?php

namespace FastUcp\Presets;

use FastUcp\Data\PaymentHandler;

/**
 * Pre-configured Google Pay payment handler.
 */
class GooglePay
{
    public static function make(
        string $merchantName,
        string $merchantId,
        string $gateway,
        string $gatewayMerchantId,
        string $environment = 'TEST',
        array $allowedCardNetworks = ['VISA', 'MASTERCARD'],
        string $version = '2026-01-11',
    ): PaymentHandler {
        return new PaymentHandler(
            id: 'gpay',
            name: 'com.google.pay',
            version: $version,
            spec: "https://pay.google.com/gp/p/ucp/{$version}/",
            configSchema: "https://pay.google.com/gp/p/ucp/{$version}/schemas/config.json",
            instrumentSchemas: [
                "https://pay.google.com/gp/p/ucp/{$version}/schemas/card_payment_instrument.json",
            ],
            config: [
                'api_version' => 2,
                'api_version_minor' => 0,
                'environment' => $environment,
                'merchant_info' => [
                    'merchant_name' => $merchantName,
                    'merchant_id' => $merchantId,
                ],
                'allowed_payment_methods' => [
                    [
                        'type' => 'CARD',
                        'parameters' => [
                            'allowed_auth_methods' => ['PAN_ONLY', 'CRYPTOGRAM_3DS'],
                            'allowed_card_networks' => $allowedCardNetworks,
                        ],
                        'tokenization_specification' => [
                            'type' => 'PAYMENT_GATEWAY',
                            'parameters' => [
                                'gateway' => $gateway,
                                'gatewayMerchantId' => $gatewayMerchantId,
                            ],
                        ],
                    ],
                ],
            ],
        );
    }
}
