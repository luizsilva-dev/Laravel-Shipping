<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Contracts;

use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\DTOs\LabelData;
use LuizSilvaDev\LaravelShipping\DTOs\RateData;
use LuizSilvaDev\LaravelShipping\DTOs\ShipmentData;
use LuizSilvaDev\LaravelShipping\DTOs\TrackingData;

interface ShippingProviderInterface
{
    /**
     * Validate a shipping address.
     *
     * @return AddressData The validated (and possibly normalized) address.
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\InvalidAddressException
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function validateAddress(AddressData $address): AddressData;

    /**
     * Get available shipping rates for a shipment.
     *
     * @return RateData[]
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\RateException
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function rates(ShipmentData $shipment): array;

    /**
     * Create a shipment and purchase a shipping label.
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\LabelException
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function createLabel(ShipmentData $shipment): LabelData;

    /**
     * Track a shipment by tracking number.
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function track(string $trackingNumber, ?string $carrierId = null): TrackingData;

    /**
     * Void / refund a label by its provider label ID.
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function refundLabel(string $labelId): bool;

    /**
     * List available carriers connected to the account.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws \LuizSilvaDev\LaravelShipping\Exceptions\ProviderException
     */
    public function listCarriers(): array;
}
