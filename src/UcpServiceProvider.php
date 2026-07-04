<?php

namespace FastUcp;

use FastUcp\Contracts\SessionStore;
use FastUcp\Data\PaymentHandler;
use FastUcp\Store\CacheSessionStore;
use FastUcp\Store\DatabaseSessionStore;
use Illuminate\Support\ServiceProvider;

class UcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/ucp.php', 'ucp');

        $this->app->singleton(UcpManager::class, function ($app) {
            $manager = new UcpManager(
                baseUrl: config('ucp.base_url'),
                version: config('ucp.version'),
                title: config('ucp.title'),
                protocols: config('ucp.protocols', []),
            );

            $this->registerConfiguredHandlers($manager);

            return $manager;
        });

        $this->app->alias(UcpManager::class, 'ucp');

        $this->app->bind(SessionStore::class, function ($app) {
            return config('ucp.session_store') === 'database'
                ? $app->make(DatabaseSessionStore::class)
                : $app->make(CacheSessionStore::class);
        });
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/ucp.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'ucp');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ucp.php' => config_path('ucp.php'),
            ], 'ucp-config');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/ucp'),
            ], 'ucp-views');

            $this->publishes([
                __DIR__.'/../resources/js' => public_path('vendor/ucp/js'),
            ], 'ucp-assets');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'ucp-migrations');
        }
    }

    protected function registerConfiguredHandlers(UcpManager $manager): void
    {
        if ($checkout = config('ucp.handlers.checkout')) {
            $manager->registerCheckoutHandler($this->app->make($checkout));
        }

        if ($discovery = config('ucp.handlers.discovery')) {
            $manager->registerDiscoveryHandler($this->app->make($discovery));
        }

        foreach (config('ucp.payment_handlers', []) as $handler) {
            if ($handler instanceof PaymentHandler) {
                $manager->registerPaymentHandler($handler);
            } elseif (is_array($handler)) {
                $manager->registerPaymentHandler(PaymentHandler::fromArray($handler));
            } elseif (is_string($handler)) {
                $manager->registerPaymentHandler($this->app->make($handler));
            }
        }
    }
}
