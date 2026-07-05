<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class ShipmentData
{
    public function __construct(
        public readonly AddressData $shipFrom,
        public readonly AddressData $shipTo,
        public readonly ParcelData $parcel,
        public readonly ?string $carrierId = null,
        public readonly ?string $serviceCode = null,
        public readonly ?string $externalShipmentId = null,
        public readonly array $options = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            shipFrom: $data['ship_from'] instanceof AddressData
                ? $data['ship_from']
                : AddressData::fromArray($data['ship_from']),
            shipTo: $data['ship_to'] instanceof AddressData
                ? $data['ship_to']
                : AddressData::fromArray($data['ship_to']),
            parcel: $data['parcel'] instanceof ParcelData
                ? $data['parcel']
                : ParcelData::fromArray($data['parcel']),
            carrierId: $data['carrier_id'] ?? null,
            serviceCode: $data['service_code'] ?? null,
            externalShipmentId: $data['external_shipment_id'] ?? null,
            options: $data['options'] ?? [],
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'ship_from'            => $this->shipFrom->toArray(),
            'ship_to'              => $this->shipTo->toArray(),
            'parcel'               => $this->parcel->toArray(),
            'carrier_id'           => $this->carrierId,
            'service_code'         => $this->serviceCode,
            'external_shipment_id' => $this->externalShipmentId,
            'options'              => $this->options ?: null,
        ], fn ($v) => $v !== null);
    }
}
