<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Tests\Unit\DTOs;

use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\Tests\TestCase;

class AddressDataTest extends TestCase
{
    public function test_it_creates_from_array(): void
    {
        $address = AddressData::fromArray([
            'name'        => 'John Doe',
            'street1'     => '123 Main St',
            'city'        => 'Austin',
            'state'       => 'TX',
            'postal_code' => '78701',
            'country'     => 'US',
            'company'     => 'ACME',
            'phone'       => '5551234567',
        ]);

        $this->assertSame('John Doe', $address->name);
        $this->assertSame('123 Main St', $address->street1);
        $this->assertSame('Austin', $address->city);
        $this->assertSame('TX', $address->state);
        $this->assertSame('78701', $address->postalCode);
        $this->assertSame('US', $address->country);
        $this->assertSame('ACME', $address->company);
        $this->assertSame('5551234567', $address->phone);
    }

    public function test_it_accepts_zip_as_postal_code_alias(): void
    {
        $address = AddressData::fromArray([
            'name'    => 'Jane',
            'street1' => '1 Way',
            'city'    => 'NYC',
            'state'   => 'NY',
            'zip'     => '10001',
            'country' => 'US',
        ]);

        $this->assertSame('10001', $address->postalCode);
    }

    public function test_to_array_omits_null_values(): void
    {
        $address = new AddressData(
            name: 'Test',
            street1: '1 Street',
            city: 'City',
            state: 'ST',
            postalCode: '00000',
            country: 'US',
        );

        $array = $address->toArray();

        $this->assertArrayNotHasKey('company', $array);
        $this->assertArrayNotHasKey('street2', $array);
        $this->assertArrayNotHasKey('phone', $array);
    }
}
