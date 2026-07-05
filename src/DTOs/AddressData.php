<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\DTOs;

final class AddressData
{
    public function __construct(
        public readonly string $name,
        public readonly string $street1,
        public readonly string $city,
        public readonly string $state,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?string $company = null,
        public readonly ?string $street2 = null,
        public readonly ?string $street3 = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?bool $isResidential = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            street1: $data['street1'],
            city: $data['city'],
            state: $data['state'],
            postalCode: $data['postal_code'] ?? $data['postalCode'] ?? $data['zip'] ?? '',
            country: $data['country'],
            company: $data['company'] ?? null,
            street2: $data['street2'] ?? null,
            street3: $data['street3'] ?? null,
            phone: $data['phone'] ?? null,
            email: $data['email'] ?? null,
            isResidential: $data['is_residential'] ?? $data['isResidential'] ?? null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'name'           => $this->name,
            'company'        => $this->company,
            'street1'        => $this->street1,
            'street2'        => $this->street2,
            'street3'        => $this->street3,
            'city'           => $this->city,
            'state'          => $this->state,
            'postal_code'    => $this->postalCode,
            'country'        => $this->country,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'is_residential' => $this->isResidential,
        ], fn ($v) => $v !== null);
    }
}
