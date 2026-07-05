<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Facades;

use Illuminate\Support\Facades\Facade;
use LuizSilvaDev\LaravelShipping\Contracts\ShippingProviderInterface;

/**
 * @method static ShippingProviderInterface driver(string $driver = null)
 * @method static \LuizSilvaDev\LaravelShipping\DTOs\AddressData validateAddress(\LuizSilvaDev\LaravelShipping\DTOs\AddressData $address)
 * @method static \LuizSilvaDev\LaravelShipping\DTOs\RateData[] rates(\LuizSilvaDev\LaravelShipping\DTOs\ShipmentData $shipment)
 * @method static \LuizSilvaDev\LaravelShipping\DTOs\LabelData createLabel(\LuizSilvaDev\LaravelShipping\DTOs\ShipmentData $shipment)
 * @method static \LuizSilvaDev\LaravelShipping\DTOs\TrackingData track(string $trackingNumber, ?string $carrierId = null)
 * @method static bool refundLabel(string $labelId)
 * @method static array listCarriers()
 *
 * @see \LuizSilvaDev\LaravelShipping\ShippingManager
 */
class Shipping extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'shipping';
    }
}
