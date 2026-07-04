<?php

namespace FastUcp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerCheckoutHandler(\FastUcp\Contracts\CheckoutHandler $handler)
 * @method static void registerDiscoveryHandler(\FastUcp\Contracts\DiscoveryHandler $handler)
 * @method static void registerCapability(\FastUcp\Data\Capability $capability)
 * @method static void registerPaymentHandler(\FastUcp\Data\PaymentHandler $handler)
 * @method static \FastUcp\Data\UcpDiscoveryProfile buildManifest()
 * @method static array createUcpContext()
 * @method static mixed callHandler(string $method, ?string $sessionId, array $params)
 * @method static array toolDefinitions()
 * @method static \FastUcp\Data\PaymentHandler[] paymentHandlers()
 * @method static string baseUrl()
 * @method static string version()
 * @method static string title()
 *
 * @see \FastUcp\UcpManager
 */
class Ucp extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \FastUcp\UcpManager::class;
    }
}
