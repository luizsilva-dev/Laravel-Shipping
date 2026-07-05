<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Tests;

use LuizSilvaDev\LaravelShipping\LaravelShippingServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelShippingServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Shipping' => \LuizSilvaDev\LaravelShipping\Facades\Shipping::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shipping.default', 'shipstation');
        $app['config']->set('shipping.drivers.shipstation', [
            'driver'   => 'shipstation',
            'api_key'  => 'test-shipstation-key',
            'sandbox'  => true,
            'base_url' => 'https://api-stage.shipstation.com',
        ]);
        $app['config']->set('shipping.drivers.shippo', [
            'driver'   => 'shippo',
            'api_key'  => 'test-shippo-key',
            'sandbox'  => true,
            'base_url' => 'https://api.goshippo.com',
        ]);
        $app['config']->set('shipping.drivers.easypost', [
            'driver'   => 'easypost',
            'api_key'  => 'EZTKtest_key',
            'sandbox'  => true,
            'base_url' => 'https://api.easypost.com',
        ]);
    }
}
