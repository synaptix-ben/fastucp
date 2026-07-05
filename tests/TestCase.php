<?php

namespace FastUcp\Tests;

use FastUcp\UcpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [UcpServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('ucp.base_url', 'http://merchant.test');
        $app['config']->set('ucp.title', 'Test Merchant');
        $app['config']->set('ucp.version', '2026-01-11');
        $app['config']->set('ucp.currency', 'USD');
        $app['config']->set('ucp.protocols.mcp', true);
        $app['config']->set('ucp.protocols.a2a', true);
        $app['config']->set('ucp.protocols.embedded', true);
        $app['config']->set('ucp.universal_cart.enabled', true);
        $app['config']->set('ucp.handlers.checkout', \FastUcp\Tests\Support\FakeCheckoutHandler::class);
        $app['config']->set('ucp.handlers.discovery', \FastUcp\Tests\Support\FakeDiscoveryHandler::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
