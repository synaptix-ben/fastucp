<?php

use FastUcp\Http\Controllers\A2aController;
use FastUcp\Http\Controllers\CheckoutController;
use FastUcp\Http\Controllers\DiscoveryController;
use FastUcp\Http\Controllers\EmbeddedCheckoutController;
use FastUcp\Http\Controllers\McpController;
use FastUcp\Http\Controllers\UniversalCartController;
use FastUcp\Http\Middleware\UcpSigningMiddleware;
use Illuminate\Support\Facades\Route;

$middleware = ['api'];
if (config('ucp.signing.enabled')) {
    $middleware[] = UcpSigningMiddleware::class;
}

Route::middleware($middleware)->group(function () {

    // --- Discovery (always active) -------------------------------------
    Route::get('/.well-known/ucp', [DiscoveryController::class, 'manifest'])
        ->name('ucp.manifest');

    // --- REST checkout ---------------------------------------------------
    Route::post('/ucp/checkout-sessions', [CheckoutController::class, 'create'])
        ->name('ucp.checkout.create');
    Route::patch('/ucp/checkout-sessions/{id}', [CheckoutController::class, 'update'])
        ->name('ucp.checkout.update');
    Route::post('/ucp/checkout-sessions/{id}/complete', [CheckoutController::class, 'complete'])
        ->name('ucp.checkout.complete');

    // --- MCP (JSON-RPC 2.0) ----------------------------------------------
    if (config('ucp.protocols.mcp')) {
        Route::post('/ucp/mcp', [McpController::class, 'handle'])->name('ucp.mcp');
        Route::get('/ucp/mcp', [McpController::class, 'status'])->name('ucp.mcp.status');
    }

    // --- A2A (Agent-to-Agent) ---------------------------------------------
    if (config('ucp.protocols.a2a')) {
        Route::get('/.well-known/agent-card.json', [A2aController::class, 'agentCard'])
            ->name('ucp.a2a.agent-card');
        Route::post('/ucp/agent/message', [A2aController::class, 'handleMessage'])
            ->name('ucp.a2a.message');
    }

    // --- Universal Cart (needs session state) ------------------------------
    if (config('ucp.universal_cart.enabled')) {
        Route::middleware('web')->prefix('ucp/cart')->group(function () {
            Route::get('/', [UniversalCartController::class, 'index'])->name('ucp.cart');
            Route::get('/view', [UniversalCartController::class, 'view'])->name('ucp.cart.view');
            Route::post('/items', [UniversalCartController::class, 'addItem'])->name('ucp.cart.add');
            Route::patch('/items/{id}', [UniversalCartController::class, 'updateItem'])->name('ucp.cart.update');
            Route::delete('/items/{id}', [UniversalCartController::class, 'removeItem'])->name('ucp.cart.remove');
            Route::post('/checkout', [UniversalCartController::class, 'checkout'])->name('ucp.cart.checkout');
        });
    }
});

// --- Embedded Checkout (rendered in the host's iframe; no signing) -------
if (config('ucp.protocols.embedded')) {
    Route::get('/ucp/embedded-checkout/{sessionId}', [EmbeddedCheckoutController::class, 'show'])
        ->name('ucp.embedded-checkout');
}
