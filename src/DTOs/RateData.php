<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class RateData
{
    public function __construct(
        public readonly string $rateId,
        public readonly string $carrierId,
        public readonly string $carrierCode,
        public readonly string $serviceCode,
        public readonly string $serviceType,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?int $deliveryDays = null,
        public readonly ?string $estimatedDeliveryDate = null,
        public readonly array $warnings = [],
        public readonly array $errors = [],
        public readonly array $raw = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            rateId: $data['rate_id'] ?? '',
            carrierId: $data['carrier_id'] ?? '',
            carrierCode: $data['carrier_code'] ?? '',
            serviceCode: $data['service_code'] ?? '',
            serviceType: $data['service_type'] ?? '',
            amount: (float) ($data['shipping_amount']['amount'] ?? $data['amount'] ?? 0),
            currency: $data['shipping_amount']['currency'] ?? $data['currency'] ?? 'USD',
            deliveryDays: isset($data['delivery_days']) ? (int) $data['delivery_days'] : null,
            estimatedDeliveryDate: $data['estimated_delivery_date'] ?? null,
            warnings: $data['warning_messages'] ?? $data['warnings'] ?? [],
            errors: $data['error_messages'] ?? $data['errors'] ?? [],
            raw: $data,
        );
    }

    public function toArray(): array
    {
        return [
            'rate_id'                 => $this->rateId,
            'carrier_id'              => $this->carrierId,
            'carrier_code'            => $this->carrierCode,
            'service_code'            => $this->serviceCode,
            'service_type'            => $this->serviceType,
            'amount'                  => $this->amount,
            'currency'                => $this->currency,
            'delivery_days'           => $this->deliveryDays,
            'estimated_delivery_date' => $this->estimatedDeliveryDate,
        ];
    }
}
