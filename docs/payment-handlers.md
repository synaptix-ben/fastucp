# Payment Handlers

UCP payment handlers describe *how* an agent can collect a payment instrument for your store. They are advertised in the discovery manifest and echoed in every checkout response's `payment.handlers`.

## Presets

### Google Pay

```php
// config/ucp.php
'payment_handlers' => [
    \FastUcp\Presets\GooglePay::make(
        merchantName: 'My Store',
        merchantId: env('GPAY_MERCHANT_ID'),
        gateway: 'stripe',
        gatewayMerchantId: env('STRIPE_ACCOUNT_ID'),
        environment: 'PRODUCTION',
    ),
],
```

### Stripe

Uses the platform-tokenizer pattern: the agent surface collects payment through Stripe and hands you a tokenized credential bound to the checkout.

```php
'payment_handlers' => [
    \FastUcp\Presets\Stripe::make(
        publishableKey: env('STRIPE_KEY'),
        merchantAccountId: env('STRIPE_ACCOUNT_ID'),
        environment: 'live',
        paymentMethodTypes: ['card', 'link'],
    ),
],
```

On `completeCheckout`, the `payment` array contains the credential the agent obtained — verify/capture it with Stripe's PHP SDK before creating the order:

```php
public function completeCheckout(string $id, array $payment): Order
{
    $intent = \Stripe\PaymentIntent::create([
        'amount' => $total,
        'currency' => strtolower($currency),
        'payment_method' => $payment['token'],
        'confirm' => true,
    ]);
    // ...
}
```

## Custom handlers

Any PSP can be described with the generic DTO:

```php
use FastUcp\Data\PaymentHandler;

'payment_handlers' => [
    new PaymentHandler(
        id: 'mypsp',
        name: 'com.mypsp.pay',
        version: '2026-01-11',
        spec: 'https://docs.mypsp.com/ucp',
        configSchema: 'https://docs.mypsp.com/ucp/schemas/config.json',
        instrumentSchemas: ['https://docs.mypsp.com/ucp/schemas/card.json'],
        config: ['merchant_ref' => '...'],
    ),
],
```

Handlers can also be registered at runtime — e.g. per-tenant — from a service provider:

```php
app(\FastUcp\UcpManager::class)->registerPaymentHandler($handler);
```
