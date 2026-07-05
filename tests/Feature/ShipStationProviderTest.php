<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Tests\Feature;

use Illuminate\Support\Facades\Http;
use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\DTOs\LabelData;
use LuizSilvaDev\LaravelShipping\DTOs\ParcelData;
use LuizSilvaDev\LaravelShipping\DTOs\RateData;
use LuizSilvaDev\LaravelShipping\DTOs\ShipmentData;
use LuizSilvaDev\LaravelShipping\DTOs\TrackingData;
use LuizSilvaDev\LaravelShipping\Exceptions\InvalidAddressException;
use LuizSilvaDev\LaravelShipping\Exceptions\RateException;
use LuizSilvaDev\LaravelShipping\Facades\Shipping;
use LuizSilvaDev\LaravelShipping\Tests\TestCase;

class ShipStationProviderTest extends TestCase
{
    private AddressData $from;
    private AddressData $to;
    private ParcelData $parcel;
    private ShipmentData $shipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->from = new AddressData(
            name: 'ShipStation Team',
            street1: '4301 Bull Creek Road',
            city: 'Austin',
            state: 'TX',
            postalCode: '78731',
            country: 'US',
            company: 'ShipStation',
            phone: '2223334444',
        );

        $this->to = new AddressData(
            name: 'The President',
            street1: '1600 Pennsylvania Avenue NW',
            city: 'Washington',
            state: 'DC',
            postalCode: '20500',
            country: 'US',
            phone: '2223334444',
        );

        $this->parcel = new ParcelData(
            weight: 6.0,
            length: 10.0,
            width: 8.0,
            height: 4.0,
            weightUnit: 'ounce',
            dimensionUnit: 'inch',
        );

        $this->shipment = new ShipmentData(
            shipFrom: $this->from,
            shipTo: $this->to,
            parcel: $this->parcel,
            carrierId: 'se-123890',
        );
    }

    public function test_validate_address_returns_normalized_address(): void
    {
        Http::fake([
            '*/v2/addresses/validate' => Http::response([
                'status'          => 'verified',
                'original_address' => [
                    'name'           => 'The President',
                    'address_line1'  => '1600 Pennsylvania Ave NW',
                    'city_locality'  => 'Washington',
                    'state_province' => 'DC',
                    'postal_code'    => '20500',
                    'country_code'   => 'US',
                ],
                'matched_address' => [
                    'name'                          => 'THE PRESIDENT',
                    'address_line1'                 => '1600 PENNSYLVANIA AVE NW',
                    'city_locality'                 => 'WASHINGTON',
                    'state_province'                => 'DC',
                    'postal_code'                   => '20500-0003',
                    'country_code'                  => 'US',
                    'address_residential_indicator' => 'no',
                ],
                'messages' => [],
            ], 200),
        ]);

        $result = Shipping::driver('shipstation')->validateAddress($this->to);

        $this->assertInstanceOf(AddressData::class, $result);
        $this->assertSame('THE PRESIDENT', $result->name);
        $this->assertSame('20500-0003', $result->postalCode);
    }

    public function test_validate_address_throws_on_error_status(): void
    {
        Http::fake([
            '*/v2/addresses/validate' => Http::response([
                'status'   => 'error',
                'messages' => [
                    ['message' => 'Address not found', 'type' => 'error'],
                ],
            ], 200),
        ]);

        $this->expectException(InvalidAddressException::class);

        Shipping::driver('shipstation')->validateAddress($this->to);
    }

    public function test_rates_returns_array_of_rate_data(): void
    {
        Http::fake([
            '*/v2/rates' => Http::response([
                'rate_response' => [
                    'rates' => [
                        [
                            'rate_id'       => 'se-rate-abc123',
                            'carrier_id'    => 'se-123890',
                            'carrier_code'  => 'usps',
                            'service_code'  => 'usps_priority_mail',
                            'service_type'  => 'USPS Priority Mail',
                            'shipping_amount' => ['amount' => 7.99, 'currency' => 'USD'],
                            'delivery_days' => 2,
                        ],
                        [
                            'rate_id'       => 'se-rate-def456',
                            'carrier_id'    => 'se-123890',
                            'carrier_code'  => 'usps',
                            'service_code'  => 'usps_first_class_mail',
                            'service_type'  => 'USPS First Class Mail',
                            'shipping_amount' => ['amount' => 3.99, 'currency' => 'USD'],
                            'delivery_days' => 5,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $rates = Shipping::driver('shipstation')->rates($this->shipment);

        $this->assertIsArray($rates);
        $this->assertCount(2, $rates);
        $this->assertInstanceOf(RateData::class, $rates[0]);
        $this->assertSame('se-rate-abc123', $rates[0]->rateId);
        $this->assertSame(7.99, $rates[0]->amount);
        $this->assertSame('USD', $rates[0]->currency);
        $this->assertSame(2, $rates[0]->deliveryDays);
    }

    public function test_rates_throws_rate_exception_when_no_rates(): void
    {
        Http::fake([
            '*/v2/rates' => Http::response([
                'rate_response' => ['rates' => []],
            ], 200),
        ]);

        $this->expectException(RateException::class);

        Shipping::driver('shipstation')->rates($this->shipment);
    }

    public function test_create_label_returns_label_data(): void
    {
        Http::fake([
            '*/v2/labels' => Http::response([
                'label_id'        => 'se-label-xyz789',
                'status'          => 'completed',
                'tracking_number' => '1Z999AA10123456784',
                'carrier_id'      => 'se-123890',
                'carrier_code'    => 'ups',
                'service_code'    => 'ups_ground',
                'shipment_id'     => 'se-ship-001',
                'label_download'  => [
                    'href' => 'https://api.shipstation.com/v2/downloads/label-xyz789.pdf',
                ],
                'shipping_cost'   => ['amount' => 12.50, 'currency' => 'USD'],
                'created_at'      => '2025-01-01T12:00:00Z',
            ], 200),
        ]);

        $label = Shipping::driver('shipstation')->createLabel($this->shipment);

        $this->assertInstanceOf(LabelData::class, $label);
        $this->assertSame('se-label-xyz789', $label->labelId);
        $this->assertSame('1Z999AA10123456784', $label->trackingNumber);
        $this->assertSame(12.50, $label->cost);
        $this->assertSame('USD', $label->currency);
        $this->assertStringContainsString('label-xyz789.pdf', $label->labelUrl);
    }

    public function test_track_returns_tracking_data(): void
    {
        Http::fake([
            '*/v2/labels/*/track' => Http::response([
                'tracking_number'   => '1Z999AA10123456784',
                'status_code'       => 'DE',
                'status_description' => 'Delivered',
                'carrier_id'        => 'se-123890',
                'estimated_delivery_date' => '2025-01-03T18:00:00Z',
                'actual_delivery_date'    => '2025-01-03T14:22:00Z',
                'events'            => [
                    [
                        'occurred_at'  => '2025-01-03T14:22:00Z',
                        'description'  => 'Delivered, In/At Mailbox',
                        'city_locality' => 'Washington',
                        'state_province' => 'DC',
                        'country_code'  => 'US',
                    ],
                ],
            ], 200),
        ]);

        $tracking = Shipping::driver('shipstation')->track('se-label-xyz789');

        $this->assertInstanceOf(TrackingData::class, $tracking);
        $this->assertSame('1Z999AA10123456784', $tracking->trackingNumber);
        $this->assertSame('DE', $tracking->status);
        $this->assertCount(1, $tracking->events);
    }

    public function test_refund_label_returns_true_on_success(): void
    {
        Http::fake([
            '*/v2/labels/*/void' => Http::response([
                'approved' => true,
                'message'  => 'Request for refund submitted.',
            ], 200),
        ]);

        $result = Shipping::driver('shipstation')->refundLabel('se-label-xyz789');

        $this->assertTrue($result);
    }

    public function test_list_carriers_returns_array(): void
    {
        Http::fake([
            '*/v2/carriers' => Http::response([
                'carriers' => [
                    [
                        'carrier_id'   => 'se-123890',
                        'carrier_code' => 'usps',
                        'friendly_name' => 'USPS',
                        'account_number' => null,
                        'balance'       => ['currency' => 'USD', 'amount' => 50.00],
                    ],
                ],
            ], 200),
        ]);

        $carriers = Shipping::driver('shipstation')->listCarriers();

        $this->assertIsArray($carriers);
        $this->assertCount(1, $carriers);
        $this->assertSame('usps', $carriers[0]['carrier_code']);
    }

    public function test_api_error_throws_provider_exception(): void
    {
        Http::fake([
            '*/v2/rates' => Http::response([
                'errors' => [
                    ['message' => 'The carrier_id is invalid.'],
                ],
            ], 400),
        ]);

        $this->expectException(\LuizSilvaDev\LaravelShipping\Exceptions\ProviderException::class);

        Shipping::driver('shipstation')->rates($this->shipment);
    }
}
