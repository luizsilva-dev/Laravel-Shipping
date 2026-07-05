<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class LabelData
{
    public function __construct(
        public readonly string $labelId,
        public readonly string $status,
        public readonly string $trackingNumber,
        public readonly string $labelUrl,
        public readonly float $cost,
        public readonly string $currency,
        public readonly ?string $carrierId = null,
        public readonly ?string $carrierCode = null,
        public readonly ?string $serviceCode = null,
        public readonly ?string $shipmentId = null,
        public readonly ?string $createdAt = null,
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            labelId: $data['label_id'] ?? $data['object_id'] ?? '',
            status: $data['status'] ?? $data['label_status'] ?? 'unknown',
            trackingNumber: $data['tracking_number'] ?? '',
            labelUrl: $data['label_download']['href']
                ?? $data['label_url']
                ?? $data['label_download_url']
                ?? '',
            cost: (float) ($data['shipping_cost']['amount']
                ?? $data['rate']
                ?? $data['cost']
                ?? 0),
            currency: $data['shipping_cost']['currency'] ?? $data['currency'] ?? 'USD',
            carrierId: $data['carrier_id'] ?? null,
            carrierCode: $data['carrier_code'] ?? null,
            serviceCode: $data['service_code'] ?? null,
            shipmentId: $data['shipment_id'] ?? null,
            createdAt: $data['created_at'] ?? $data['object_created'] ?? null,
            raw: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'label_id'        => $this->labelId,
            'status'          => $this->status,
            'tracking_number' => $this->trackingNumber,
            'label_url'       => $this->labelUrl,
            'cost'            => $this->cost,
            'currency'        => $this->currency,
            'carrier_id'      => $this->carrierId,
            'carrier_code'    => $this->carrierCode,
            'service_code'    => $this->serviceCode,
            'shipment_id'     => $this->shipmentId,
            'created_at'      => $this->createdAt,
        ];
    }
}
