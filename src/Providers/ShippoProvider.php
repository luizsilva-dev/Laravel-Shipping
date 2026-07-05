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
 * Shippo REST API provider.
 *
 * Authentication: ShippoToken <token> header
 * Base URL: https://api.goshippo.com
 *
 * @see https://docs.goshippo.com/shippoapi/public-api
 */
class ShippoProvider extends AbstractProvider
{
    protected function providerName(): string
    {
        return 'shippo';
    }

    protected function buildHttpClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Authorization' => 'ShippoToken ' . $this->config['api_key'],
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])
            ->timeout(30);
    }

    /**
     * Validate an address via POST /addresses/ + POST /addresses/{id}/validate.
     *
     * Shippo validates addresses by first creating an address object with
     * validate=true, then checking validation_results.
     *
     * @see https://docs.goshippo.com/api-reference/addresses
     */
    public function validateAddress(AddressData $address): AddressData
    {
        $response = $this->http()->post('/addresses/', array_filter([
            'name'           => $address->name,
            'company'        => $address->company,
            'street1'        => $address->street1,
            'street2'        => $address->street2,
            'city'           => $address->city,
            'state'          => $address->state,
            'zip'            => $address->postalCode,
            'country'        => $address->country,
            'phone'          => $address->phone,
            'email'          => $address->email,
            'is_residential' => $address->isResidential,
            'validate'       => true,
        ], fn ($v) => $v !== null));

        $this->throwIfFailed($response, 'validate address');

        $data = $response->json();

        $results = $data['validation_results'] ?? [];
        $isValid = $results['is_valid'] ?? false;

        if (! $isValid) {
            $messages = array_column($results['messages'] ?? [], 'text');
            throw new InvalidAddressException(
                message: 'Address validation failed: ' . implode(', ', $messages),
                context: $data,
            );
        }

        return new AddressData(
            name: $data['name'] ?? $address->name,
            street1: $data['street1'] ?? $address->street1,
            city: $data['city'] ?? $address->city,
            state: $data['state'] ?? $address->state,
            postalCode: $data['zip'] ?? $address->postalCode,
            country: $data['country'] ?? $address->country,
            company: $data['company'] ?? $address->company,
            street2: $data['street2'] ?? $address->street2,
            phone: $data['phone'] ?? $address->phone,
            email: $data['email'] ?? $address->email,
            isResidential: $data['is_residential'] ?? $address->isResidential,
        );
    }

    /**
     * Get shipping rates via POST /shipments/ and retrieve rates.
     *
     * Shippo requires creating a shipment first, which auto-generates rates.
     *
     * @return RateData[]
     *
     * @see https://docs.goshippo.com/api-reference/shipments
     */
    public function rates(ShipmentData $shipment): array
    {
        $fromAddress = $this->createAddress($shipment->shipFrom);
        $toAddress   = $this->createAddress($shipment->shipTo);
        $parcel      = $this->createParcel($shipment);

        $response = $this->http()->post('/shipments/', [
            'address_from'    => $fromAddress,
            'address_to'      => $toAddress,
            'parcels'         => [$parcel],
            'async'           => false,
        ]);

        $this->throwIfFailed($response, 'get rates');

        $data  = $response->json();
        $rates = $data['rates'] ?? [];

        if (empty($rates)) {
            throw new RateException(
                message: 'No rates returned from Shippo.',
                context: $data,
            );
        }

        return array_map(fn (array $rate) => new RateData(
            rateId: $rate['object_id'] ?? '',
            carrierId: $rate['carrier_account'] ?? '',
            carrierCode: $rate['provider'] ?? '',
            serviceCode: $rate['servicelevel']['token'] ?? '',
            serviceType: $rate['servicelevel']['name'] ?? '',
            amount: (float) ($rate['amount'] ?? 0),
            currency: $rate['currency'] ?? 'USD',
            deliveryDays: isset($rate['estimated_days']) ? (int) $rate['estimated_days'] : null,
            estimatedDeliveryDate: $rate['arrives_by'] ?? null,
            raw: $rate,
        ), $rates);
    }

    /**
     * Create a shipment and purchase a label via POST /transactions/.
     *
     * @see https://docs.goshippo.com/api-reference/transactions
     */
    public function createLabel(ShipmentData $shipment): LabelData
    {
        $rates = $this->rates($shipment);

        if (empty($rates)) {
            throw new LabelException('No rates available to create a label.');
        }

        $cheapestRate = collect($rates)->sortBy('amount')->first();

        $response = $this->http()->post('/transactions/', [
            'rate'            => $cheapestRate->rateId,
            'label_file_type' => 'PDF',
            'async'           => false,
        ]);

        $this->throwIfFailed($response, 'create label');

        $data = $response->json();

        if (($data['status'] ?? '') === 'ERROR') {
            throw new LabelException(
                message: 'Shippo label creation failed: ' . implode(', ', $data['messages'] ?? []),
                context: $data,
            );
        }

        return new LabelData(
            labelId: $data['object_id'] ?? '',
            status: $data['status'] ?? 'unknown',
            trackingNumber: $data['tracking_number'] ?? '',
            labelUrl: $data['label_url'] ?? '',
            cost: (float) ($cheapestRate->amount),
            currency: $cheapestRate->currency,
            carrierCode: $data['tracking_carrier'] ?? null,
            createdAt: $data['object_created'] ?? null,
            raw: $data,
        );
    }

    /**
     * Track a shipment via GET /tracks/{carrier}/{tracking_number}.
     *
     * @see https://docs.goshippo.com/docs/Tracking/Tracking
     */
    public function track(string $trackingNumber, ?string $carrierId = null): TrackingData
    {
        $carrier  = $carrierId ?? 'usps';
        $response = $this->http()->get("/tracks/{$carrier}/{$trackingNumber}");

        $this->throwIfFailed($response, 'track shipment');

        $data = $response->json();

        $events = array_map(fn (array $e) => [
            'occurred_at' => $e['status_date'] ?? null,
            'description' => $e['status_details'] ?? '',
            'city'        => $e['location']['city'] ?? null,
            'state'       => $e['location']['state'] ?? null,
            'country'     => $e['location']['country'] ?? null,
        ], $data['tracking_history'] ?? []);

        return new TrackingData(
            trackingNumber: $data['tracking_number'] ?? $trackingNumber,
            status: $data['tracking_status']['status'] ?? 'UNKNOWN',
            statusDescription: $data['tracking_status']['status_details'] ?? '',
            carrierId: $carrier,
            carrierCode: $carrier,
            estimatedDeliveryDate: $data['eta'] ?? null,
            events: $events,
            raw: $data,
        );
    }

    /**
     * Request a refund via POST /refunds/.
     *
     * @see https://docs.goshippo.com/api-reference/refunds
     */
    public function refundLabel(string $labelId): bool
    {
        $response = $this->http()->post('/refunds/', [
            'transaction' => $labelId,
        ]);

        $this->throwIfFailed($response, 'refund label');

        $data = $response->json();

        return in_array($data['status'] ?? '', ['queued', 'submitted', 'refunded'], true);
    }

    /**
     * List carrier accounts via GET /carrier_accounts/.
     *
     * @see https://docs.goshippo.com/api-reference/carrier-accounts
     */
    public function listCarriers(): array
    {
        $response = $this->http()->get('/carrier_accounts/');

        $this->throwIfFailed($response, 'list carriers');

        return $response->json('results') ?? $response->json() ?? [];
    }

    private function createAddress(AddressData $address): array
    {
        return array_filter([
            'name'           => $address->name,
            'company'        => $address->company,
            'street1'        => $address->street1,
            'street2'        => $address->street2,
            'city'           => $address->city,
            'state'          => $address->state,
            'zip'            => $address->postalCode,
            'country'        => $address->country,
            'phone'          => $address->phone,
            'email'          => $address->email,
            'is_residential' => $address->isResidential,
        ], fn ($v) => $v !== null);
    }

    private function createParcel(ShipmentData $shipment): array
    {
        return [
            'length'        => $shipment->parcel->length,
            'width'         => $shipment->parcel->width,
            'height'        => $shipment->parcel->height,
            'distance_unit' => $shipment->parcel->dimensionUnit === 'inch' ? 'in' : 'cm',
            'weight'        => $shipment->parcel->weight,
            'mass_unit'     => $shipment->parcel->weightUnit === 'ounce' ? 'oz' : $shipment->parcel->weightUnit,
        ];
    }

    protected function extractErrorMessage(array $body): string
    {
        if (isset($body['messages']) && is_array($body['messages'])) {
            return implode('; ', array_column($body['messages'], 'text'));
        }

        return $body['detail'] ?? $body['message'] ?? '';
    }
}
