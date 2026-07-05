<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class TrackingData
{
    public function __construct(
        public readonly string $trackingNumber,
        public readonly string $status,
        public readonly string $statusDescription,
        public readonly ?string $carrierId = null,
        public readonly ?string $carrierCode = null,
        public readonly ?string $estimatedDeliveryDate = null,
        public readonly ?string $actualDeliveryDate = null,
        public readonly array $events = [],
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        $events = array_map(
            fn (array $e) => [
                'occurred_at'  => $e['occurred_at'] ?? $e['status_date'] ?? null,
                'description'  => $e['description'] ?? $e['status_description'] ?? '',
                'city'         => $e['city_locality'] ?? $e['location']['city'] ?? null,
                'state'        => $e['state_province'] ?? $e['location']['state'] ?? null,
                'country'      => $e['country_code'] ?? $e['location']['country'] ?? null,
            ],
            $data['events'] ?? $data['tracking_history'] ?? []
        );

        return new self(
            trackingNumber: $data['tracking_number'] ?? '',
            status: $data['status_code'] ?? $data['tracking_status'] ?? 'unknown',
            statusDescription: $data['status_description'] ?? $data['status'] ?? '',
            carrierId: $data['carrier_id'] ?? null,
            carrierCode: $data['carrier_code'] ?? null,
            estimatedDeliveryDate: $data['estimated_delivery_date'] ?? null,
            actualDeliveryDate: $data['actual_delivery_date'] ?? null,
            events: $events,
            raw: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'tracking_number'         => $this->trackingNumber,
            'status'                  => $this->status,
            'status_description'      => $this->statusDescription,
            'carrier_id'              => $this->carrierId,
            'carrier_code'            => $this->carrierCode,
            'estimated_delivery_date' => $this->estimatedDeliveryDate,
            'actual_delivery_date'    => $this->actualDeliveryDate,
            'events'                  => $this->events,
        ];
    }
}
