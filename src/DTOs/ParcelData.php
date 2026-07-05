<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class ParcelData
{
    public function __construct(
        public readonly float $weight,
        public readonly float $length,
        public readonly float $width,
        public readonly float $height,
        public readonly string $weightUnit = 'ounce',
        public readonly string $dimensionUnit = 'inch',
        public readonly ?string $packageCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            weight: (float) ($data['weight'] ?? 0),
            length: (float) ($data['length'] ?? 0),
            width: (float) ($data['width'] ?? 0),
            height: (float) ($data['height'] ?? 0),
            weightUnit: $data['weight_unit'] ?? $data['weightUnit'] ?? 'ounce',
            dimensionUnit: $data['dimension_unit'] ?? $data['dimensionUnit'] ?? 'inch',
            packageCode: $data['package_code'] ?? $data['packageCode'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'weight'         => $this->weight,
            'length'         => $this->length,
            'width'          => $this->width,
            'height'         => $this->height,
            'weight_unit'    => $this->weightUnit,
            'dimension_unit' => $this->dimensionUnit,
            'package_code'   => $this->packageCode,
        ], fn ($v) => $v !== null);
    }
}
