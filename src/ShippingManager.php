<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping;

use Illuminate\Support\Manager;
use LuizSilvaDev\LaravelShipping\Contracts\ShippingProviderInterface;
use LuizSilvaDev\LaravelShipping\Exceptions\ShippingException;
use LuizSilvaDev\LaravelShipping\Providers\EasyPostProvider;
use LuizSilvaDev\LaravelShipping\Providers\ShippoProvider;
use LuizSilvaDev\LaravelShipping\Providers\ShipStationProvider;

/**
 * @mixin ShippingProviderInterface
 */
class ShippingManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('shipping.default', 'shipstation');
    }

    protected function createShipstationDriver(): ShippingProviderInterface
    {
        $config = $this->getDriverConfig('shipstation');

        return new ShipStationProvider($config);
    }

    protected function createShippoDriver(): ShippingProviderInterface
    {
        $config = $this->getDriverConfig('shippo');

        return new ShippoProvider($config);
    }

    protected function createEasypostDriver(): ShippingProviderInterface
    {
        $config = $this->getDriverConfig('easypost');

        return new EasyPostProvider($config);
    }

    protected function getDriverConfig(string $driver): array
    {
        $config = $this->config->get("shipping.drivers.{$driver}");

        if (! is_array($config)) {
            throw new ShippingException("Shipping driver [{$driver}] is not configured.");
        }

        return $config;
    }
}
