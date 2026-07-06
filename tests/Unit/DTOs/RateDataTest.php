<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Tests\Unit\DTOs;

use LuizSilvaDev\LaravelShipping\DTOs\RateData;
use LuizSilvaDev\LaravelShipping\Tests\TestCase;

class RateDataTest extends TestCase
{
    public function test_from_array_maps_shipping_amount_correctly(): void
    {
        $rate = RateData::fromArray([
            'rate_id'         => 'se-321654',
            'carrier_id'      => 'se-123890',
            'carrier_code'    => 'fedex',
            'service_code'    => 'fedex_ground',
            'service_type'    => 'FedEx Ground®',
            'shipping_amount' => ['amount' => 10.10, 'currency' => 'usd'],
            'other_amount'    => ['amount' => 1.52, 'currency' => 'usd'],
            'delivery_days'   => 3,
            'warning_messages' => ['FedEx may add a surcharge.'],
            'error_messages'   => [],
        ]);

        $this->assertSame('se-321654', $rate->rateId);
        $this->assertSame(10.10, $rate->amount);
        $this->assertSame(1.52, $rate->otherAmount);
        $this->assertSame(11.62, $rate->totalAmount());
        $this->assertSame(3, $rate->deliveryDays);
    }

    public function test_currency_is_normalized_to_uppercase(): void
    {
        $rate = RateData::fromArray([
            'rate_id'         => 'se-1',
            'carrier_id'      => 'se-1',
            'carrier_code'    => 'usps',
            'service_code'    => 'usps_ground',
            'service_type'    => 'USPS Ground',
            'shipping_amount' => ['amount' => 5.00, 'currency' => 'usd'],
            'error_messages'  => [],
        ]);

        $this->assertSame('USD', $rate->currency);
    }

    public function test_total_amount_is_shipping_plus_other(): void
    {
        $rate = new RateData(
            rateId: 'r1',
            carrierId: 'c1',
            carrierCode: 'ups',
            serviceCode: 'ups_ground',
            serviceType: 'UPS Ground',
            amount: 8.50,
            currency: 'USD',
            otherAmount: 1.50,
        );

        $this->assertSame(10.0, $rate->totalAmount());
    }

    public function test_other_amount_defaults_to_zero(): void
    {
        $rate = RateData::fromArray([
            'rate_id'         => 'se-1',
            'carrier_id'      => 'se-1',
            'carrier_code'    => 'usps',
            'service_code'    => 'usps_ground',
            'service_type'    => 'USPS Ground',
            'shipping_amount' => ['amount' => 4.99, 'currency' => 'USD'],
            'error_messages'  => [],
        ]);

        $this->assertSame(0.0, $rate->otherAmount);
        $this->assertSame(4.99, $rate->totalAmount());
    }

    public function test_to_array_includes_total_amount_and_warnings(): void
    {
        $rate = RateData::fromArray([
            'rate_id'          => 'se-1',
            'carrier_id'       => 'se-1',
            'carrier_code'     => 'usps',
            'service_code'     => 'usps_ground',
            'service_type'     => 'USPS Ground',
            'shipping_amount'  => ['amount' => 5.00, 'currency' => 'USD'],
            'other_amount'     => ['amount' => 0.50, 'currency' => 'USD'],
            'warning_messages' => ['Residential surcharge may apply.'],
            'error_messages'   => [],
        ]);

        $array = $rate->toArray();

        $this->assertArrayHasKey('total_amount', $array);
        $this->assertSame(5.50, $array['total_amount']);
        $this->assertArrayHasKey('warnings', $array);
        $this->assertSame(['Residential surcharge may apply.'], $array['warnings']);
    }
}
