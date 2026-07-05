# Installation

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12

## Install

```bash
composer require fastucp/laravel
```

The service provider and `Ucp` facade are auto-discovered.

## Publish the config

```bash
php artisan vendor:publish --tag=ucp-config
```

This creates `config/ucp.php`. Set at minimum:

```env
UCP_BASE_URL=https://store.example.com
UCP_TITLE="My Store"
UCP_CURRENCY=USD
```

## Migrations

The package ships tables for checkout sessions (database store), orders, and the universal cart:

```bash
php artisan migrate
```

If you use the default cache-backed session store (`UCP_SESSION_STORE=cache`) and don't enable the universal cart, you can skip the migrations entirely.

## Optional publishes

```bash
php artisan vendor:publish --tag=ucp-views       # Blade views (embedded checkout, cart)
php artisan vendor:publish --tag=ucp-assets      # ECP bridge JS to public/vendor/ucp/js
php artisan vendor:publish --tag=ucp-migrations  # copy migrations into your app
```

When `public/vendor/ucp/js/ecp-bridge.js` exists, the embedded checkout view uses your published copy instead of the packaged one — customize freely.

## Response signing (optional)

Signing requires the JWT framework:

```bash
composer require web-token/jwt-framework
```

See [Security & Signing](security.md).

## Verify

```bash
curl -s https://store.example.com/.well-known/ucp | jq .ucp.version
```
