<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\DTOs\LabelData;
use LuizSilvaDev\LaravelShipping\DTOs\RateData;
use LuizSilvaDev\LaravelShipping\DTOs\ShipmentData;
use LuizSilvaDev\LaravelShipping\DTOs\TrackingData;
use LuizSilvaDev\LaravelShipping\Exceptions\InvalidAddressException;
use LuizSilvaDev\LaravelShipping\Exceptions\LabelException;
use LuizSilvaDev\LaravelShipping\Exceptions\ProviderException;
use LuizSilvaDev\LaravelShipping\Exceptions\RateException;

/**
 * ShipStation API v2 provider.
 *
 * Authentication: API-Key header
 * Base URL: https://api.shipstation.com
 * Sandbox: https://api-stage.shipstation.com
 *
 * @see https://docs.shipstation.com
 */
class ShipStationProvider extends AbstractProvider
{
    protected function providerName(): string
    {
        return 'shipstation';
    }

    protected function buildHttpClient(): PendingRequest
    {
        $baseUrl = $this->isSandbox()
            ? 'https://api-stage.shipstation.com'
            : ($this->config['base_url'] ?? 'https://api.shipstation.com');

        return Http::baseUrl($baseUrl)
            ->withHeaders([
                'API-Key'      => $this->config['api_key'],
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout(30);
    }

    protected function isSandbox(): bool
    {
        return (bool) ($this->config['sandbox'] ?? true);
    }

    /**
     * Validate an address via POST /v2/addresses/validate.
     *
     * NOTE: Address validation is only available on paid ShipStation plans.
     * Free plan accounts will receive a 403 error from the API.
     *
     * @see https://docs.shipstation.com/openapi/addresses/validate_address
     */
    public function validateAddress(AddressData $address): AddressData
    {
        $response = $this->http()->post('/v2/addresses/validate', [
            'name'                        => $address->name,
            'company_name'                => $address->company,
            'address_line1'               => $address->street1,
            'address_line2'               => $address->street2,
            'address_line3'               => $address->street3,
            'city_locality'               => $address->city,
            'state_province'              => $address->state,
            'postal_code'                 => $address->postalCode,
            'country_code'                => $address->country,
            'phone'                       => $address->phone,
            'address_residential_indicator' => $address->isResidential === true ? 'yes'
                : ($address->isResidential === false ? 'no' : 'unknown'),
        ]);

        if ($response->status() === 403) {
            throw ProviderException::unsupportedFeature(
                provider: $this->providerName(),
                feature: 'address validation (not available on free plan — upgrade your ShipStation account)',
            );
        }

        $this->throwIfFailed($response, 'validate address');

        $data = $response->json() ?? [];

        $status = $data['status'] ?? 'error';

        if ($status === 'error') {
            throw new InvalidAddressException(
                message: 'Address validation failed: ' . implode(', ', array_column($data['messages'] ?? [], 'message')),
                context: $data,
            );
        }

        $normalized = $data['matched_address'] ?? $data['original_address'] ?? [];

        return new AddressData(
            name: $normalized['name'] ?? $address->name,
            street1: $normalized['address_line1'] ?? $address->street1,
            city: $normalized['city_locality'] ?? $address->city,
            state: $normalized['state_province'] ?? $address->state,
            postalCode: $normalized['postal_code'] ?? $address->postalCode,
            country: $normalized['country_code'] ?? $address->country,
            company: $normalized['company_name'] ?? $address->company,
            street2: $normalized['address_line2'] ?? $address->street2,
            phone: $address->phone,
            email: $address->email,
            isResidential: ($normalized['address_residential_indicator'] ?? 'unknown') === 'yes',
        );
    }

    /**
     * Get shipping rates via POST /v2/rates.
     *
     * @return RateData[]
     *
     * @see https://docs.shipstation.com/rate-shopping
     */
    public function rates(ShipmentData $shipment): array
    {
        $payload = [
            'rate_options' => $this->buildRateOptions($shipment),
            'shipment'     => $this->buildShipmentPayload($shipment),
        ];

        $response = $this->http()->post('/v2/rates', $payload);

        $this->throwIfFailed($response, 'get rates');

        $data = $response->json() ?? [];

        $rateResponse   = $data['rate_response'] ?? [];
        $rates          = $rateResponse['rates'] ?? [];
        $invalidRates   = $rateResponse['invalid_rates'] ?? [];
        $status         = $rateResponse['status'] ?? 'unknown';

        $validRates = array_filter(
            $rates,
            fn (array $r) => empty($r['error_messages'])
        );

        if (empty($validRates)) {
            $errors = array_merge(
                array_column($invalidRates, 'error_messages'),
                array_filter(array_column($rates, 'error_messages'))
            );

            $errorMessages = array_unique(array_merge(...array_map(
                fn ($e) => is_array($e) ? $e : [$e],
                $errors
            )));

            throw new RateException(
                message: 'No valid rates returned from ShipStation'
                    . ($errorMessages ? ': ' . implode('; ', $errorMessages) : ''),
                context: $data,
            );
        }

        $rates = array_values($validRates);

        return array_map(fn (array $rate) => RateData::fromArray($rate), $rates);
    }

    /**
     * Create a shipment and purchase a label via POST /v2/labels.
     *
     * @see https://docs.shipstation.com/create-labels
     */
    public function createLabel(ShipmentData $shipment): LabelData
    {
        $payload = [
            'shipment'     => $this->buildShipmentPayload($shipment),
            'test_label'   => $this->isSandbox(),
            'label_format' => 'pdf',
            'label_layout' => '4x6',
        ];

        $response = $this->http()->post('/v2/labels', $payload);

        $this->throwIfFailed($response, 'create label');

        $data = $response->json();

        if (! isset($data['label_id'])) {
            throw new LabelException(
                message: 'ShipStation did not return a label_id.',
                context: $data,
            );
        }

        return $this->mapLabel($data);
    }

    /**
     * Track a label via GET /v2/labels/{label_id}/track.
     *
     * Note: ShipStation v2 tracks by label_id, not by tracking number directly.
     * Pass the label_id as $trackingNumber for label-based tracking,
     * or pass a raw tracking number to attempt carrier tracking lookup.
     *
     * @see https://docs.shipstation.com/openapi/labels/get_tracking_log_from_label
     */
    public function track(string $trackingNumber, ?string $carrierId = null): TrackingData
    {
        $response = $this->http()->get("/v2/labels/{$trackingNumber}/track");

        $this->throwIfFailed($response, 'track shipment');

        $data = $response->json();

        return TrackingData::fromArray(array_merge($data, [
            'tracking_number' => $data['tracking_number'] ?? $trackingNumber,
            'carrier_id'      => $carrierId ?? $data['carrier_id'] ?? null,
        ]));
    }

    /**
     * Void a label via PUT /v2/labels/{label_id}/void.
     *
     * @see https://docs.shipstation.com/openapi/labels/void_label
     */
    public function refundLabel(string $labelId): bool
    {
        $response = $this->http()->put("/v2/labels/{$labelId}/void");

        $this->throwIfFailed($response, 'void label');

        $data = $response->json();

        return (bool) ($data['approved'] ?? true);
    }

    /**
     * List carriers connected to the account via GET /v2/carriers.
     *
     * @see https://docs.shipstation.com/openapi/carriers/list_carriers
     */
    public function listCarriers(): array
    {
        $response = $this->http()->get('/v2/carriers');

        $this->throwIfFailed($response, 'list carriers');

        $data = $response->json();

        return $data['carriers'] ?? $data ?? [];
    }

    private function buildRateOptions(ShipmentData $shipment): array
    {
        $options = [];

        if ($shipment->carrierId !== null) {
            $options['carrier_ids'] = [$shipment->carrierId];
        }

        if ($shipment->serviceCode !== null) {
            $options['service_codes'] = [$shipment->serviceCode];
        }

        return $options;
    }

    private function buildShipmentPayload(ShipmentData $shipment): array
    {
        return [
            'validate_address' => 'no_validation',
            'ship_from'        => $this->mapAddress($shipment->shipFrom),
            'ship_to'          => $this->mapAddress($shipment->shipTo),
            'packages'         => [
                $this->mapPackage($shipment),
            ],
        ];
    }

    private function mapAddress(AddressData $address): array
    {
        return array_filter([
            'name'                          => $address->name,
            'company_name'                  => $address->company,
            'phone'                         => $address->phone,
            'address_line1'                 => $address->street1,
            'address_line2'                 => $address->street2,
            'address_line3'                 => $address->street3,
            'city_locality'                 => $address->city,
            'state_province'                => $address->state,
            'postal_code'                   => $address->postalCode,
            'country_code'                  => $address->country,
            'address_residential_indicator' => match ($address->isResidential) {
                true    => 'yes',
                false   => 'no',
                default => 'unknown',
            },
        ], fn ($v) => $v !== null);
    }

    private function mapPackage(ShipmentData $shipment): array
    {
        $parcel = $shipment->parcel;

        $package = [
            'weight' => [
                'value' => $parcel->weight,
                'unit'  => $parcel->weightUnit,
            ],
        ];

        if ($parcel->packageCode !== null) {
            $package['package_code'] = $parcel->packageCode;
        } else {
            $package['dimensions'] = [
                'unit'   => $parcel->dimensionUnit,
                'length' => $parcel->length,
                'width'  => $parcel->width,
                'height' => $parcel->height,
            ];
        }

        return $package;
    }

    private function mapLabel(array $data): LabelData
    {
        return new LabelData(
            labelId: $data['label_id'],
            status: $data['status'] ?? 'completed',
            trackingNumber: $data['tracking_number'] ?? '',
            labelUrl: $data['label_download']['href'] ?? $data['label_download_url'] ?? '',
            cost: (float) ($data['shipping_cost']['amount'] ?? 0),
            currency: $data['shipping_cost']['currency'] ?? 'USD',
            carrierId: $data['carrier_id'] ?? null,
            carrierCode: $data['carrier_code'] ?? null,
            serviceCode: $data['service_code'] ?? null,
            shipmentId: $data['shipment_id'] ?? null,
            createdAt: $data['created_at'] ?? null,
            raw: $data,
        );
    }

    protected function extractErrorMessage(array $body): string
    {
        if (isset($body['errors']) && is_array($body['errors'])) {
            return implode('; ', array_column($body['errors'], 'message'));
        }

        return $body['message'] ?? $body['error'] ?? '';
    }
}
