<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | The public-facing base URL of your merchant server. This is used in the
    | /.well-known/ucp discovery manifest and for generating endpoint URLs.
    |
    */
    'base_url' => env('UCP_BASE_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Protocol Version
    |--------------------------------------------------------------------------
    |
    | UCP protocol version in YYYY-MM-DD format.
    |
    */
    'version' => env('UCP_VERSION', '2026-01-11'),

    /*
    |--------------------------------------------------------------------------
    | Merchant Title
    |--------------------------------------------------------------------------
    */
    'title' => env('UCP_TITLE', 'UCP Merchant'),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | ISO 4217 currency code used as the default for checkout sessions.
    |
    */
    'currency' => env('UCP_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Transport Protocols
    |--------------------------------------------------------------------------
    |
    | Enable or disable additional transport protocols beyond REST.
    |
    */
    'protocols' => [
        'mcp' => env('UCP_ENABLE_MCP', false),
        'a2a' => env('UCP_ENABLE_A2A', false),
        'embedded' => env('UCP_ENABLE_EMBEDDED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Universal Cart
    |--------------------------------------------------------------------------
    |
    | Enable the universal cart feature for single or multi-merchant carts.
    |
    */
    'universal_cart' => [
        'enabled' => env('UCP_UNIVERSAL_CART', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Signing (JWS)
    |--------------------------------------------------------------------------
    |
    | Enable JWS response signing with ES256. Requires web-token/jwt-framework.
    | The key should be a JWK JSON string (private key, including 'd').
    |
    */
    'signing' => [
        'enabled' => env('UCP_SIGNING_ENABLED', false),
        'key' => env('UCP_SIGNING_KEY'),
        'algorithm' => 'ES256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Store
    |--------------------------------------------------------------------------
    |
    | How checkout sessions are persisted. Options: 'cache', 'database'.
    |
    */
    'session_store' => env('UCP_SESSION_STORE', 'cache'),

    /*
    |--------------------------------------------------------------------------
    | Handlers
    |--------------------------------------------------------------------------
    |
    | Register your handler classes that implement the UCP contracts.
    |
    */
    'handlers' => [
        'checkout' => null, // App\Ucp\MyCheckoutHandler::class
        'discovery' => null, // App\Ucp\MyDiscoveryHandler::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Handlers
    |--------------------------------------------------------------------------
    |
    | Pre-configured payment handler presets. Each entry may be a
    | PaymentHandler instance, an array, or a container-resolvable class.
    |
    | Example:
    |   \FastUcp\Presets\GooglePay::make('My Store', 'merchant_123', 'stripe', 'acct_xxx'),
    |   \FastUcp\Presets\Stripe::make(env('STRIPE_KEY'), env('STRIPE_ACCOUNT_ID')),
    |
    */
    'payment_handlers' => [],

    /*
    |--------------------------------------------------------------------------
    | Embedded Checkout
    |--------------------------------------------------------------------------
    |
    | Configuration for the Embedded Checkout Protocol (ECP).
    |
    */
    'embedded' => [
        // Use Shopify's <shopify-checkout> web component instead of the
        // default Blade checkout UI (for Shopify merchants).
        'use_shopify_component' => false,
        // Shopify checkout URL template used when the component is enabled.
        'shopify_checkout_url' => env('UCP_SHOPIFY_CHECKOUT_URL'),
        // Allowed host origins for postMessage. null allows any origin
        // (the bridge still echoes and pins the first origin that
        // completes the handshake); an array acts as a whitelist.
        'allowed_origins' => null,
    ],

];
