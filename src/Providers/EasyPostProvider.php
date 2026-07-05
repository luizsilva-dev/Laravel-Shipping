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
 * EasyPost API v2 provider.
 *
 * Authentication: HTTP Basic Auth (API key as username, empty password)
 * Base URL: https://api.easypost.com
 *
 * @see https://docs.easypost.com
 */
class EasyPostProvider extends AbstractProvider
{
    protected function providerName(): string
    {
        return 'easypost';
    }

    protected function buildHttpClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withBasicAuth($this->config['api_key'], '')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
            ])
            ->timeout(30);
    }

    /**
     * Validate an address via POST /v2/addresses (with verify param).
     *
     * EasyPost validates addresses inline during creation when verify=true.
     *
     * @see https://docs.easypost.com/docs/addresses
     */
    public function validateAddress(AddressData $address): AddressData
    {
        $response = $this->http()->post('/v2/addresses', [
            'address' => array_filter([
                'name'    => $address->name,
                'company' => $address->company,
                'street1' => $address->street1,
                'street2' => $address->street2,
                'city'    => $address->city,
                'state'   => $address->state,
                'zip'     => $address->postalCode,
                'country' => $address->country,
                'phone'   => $address->phone,
                'email'   => $address->email,
            ], fn ($v) => $v !== null),
            'verify' => true,
        ]);

        $this->throwIfFailed($response, 'validate address');

        $data = $response->json();

        $verifications = $data['verifications'] ?? [];
        $delivery      = $verifications['delivery'] ?? [];
        $isDeliverable = $delivery['success'] ?? false;

        if (! $isDeliverable) {
            $errors = array_column($delivery['errors'] ?? [], 'message');
            throw new InvalidAddressException(
                message: 'Address validation failed: ' . implode(', ', $errors),
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
        );
    }

    /**
     * Create a shipment to get rates via POST /v2/shipments.
     *
     * EasyPost returns rates immediately on shipment creation.
     *
     * @return RateData[]
     *
     * @see https://docs.easypost.com/docs/shipments
     */
    public function rates(ShipmentData $shipment): array
    {
        $payload = $this->buildShipmentPayload($shipment);

        $response = $this->http()->post('/v2/shipments', $payload);

        $this->throwIfFailed($response, 'get rates');

        $data  = $response->json();
        $rates = $data['rates'] ?? [];

        if (empty($rates)) {
            throw new RateException(
                message: 'No rates returned from EasyPost.',
                context: $data,
            );
        }

        return array_map(fn (array $rate) => new RateData(
            rateId: $rate['id'] ?? '',
            carrierId: $rate['carrier_account_id'] ?? '',
            carrierCode: $rate['carrier'] ?? '',
            serviceCode: $rate['service'] ?? '',
            serviceType: $rate['service'] ?? '',
            amount: (float) ($rate['rate'] ?? 0),
            currency: $rate['currency'] ?? 'USD',
            deliveryDays: isset($rate['delivery_days']) ? (int) $rate['delivery_days'] : null,
            estimatedDeliveryDate: $rate['delivery_date'] ?? null,
            raw: $rate,
        ), $rates);
    }

    /**
     * Create a shipment and buy a label via POST /v2/shipments + POST /v2/shipments/{id}/buy.
     *
     * EasyPost requires two calls: create shipment (rates), then buy with chosen rate.
     *
     * @see https://docs.easypost.com/docs/shipments
     */
    public function createLabel(ShipmentData $shipment): LabelData
    {
        $payload = $this->buildShipmentPayload($shipment);

        $createResponse = $this->http()->post('/v2/shipments', $payload);
        $this->throwIfFailed($createResponse, 'create shipment');

        $shipmentData = $createResponse->json();
        $rates        = $shipmentData['rates'] ?? [];

        if (empty($rates)) {
            throw new LabelException('No rates available from EasyPost to buy a label.');
        }

        usort($rates, fn ($a, $b) => (float) $a['rate'] <=> (float) $b['rate']);
        $cheapestRate = $rates[0];

        $shipmentId = $shipmentData['id'];

        $buyResponse = $this->http()->post("/v2/shipments/{$shipmentId}/buy", [
            'rate' => ['id' => $cheapestRate['id']],
        ]);

        $this->throwIfFailed($buyResponse, 'buy label');

        $data = $buyResponse->json();

        $postageLabel = $data['postage_label'] ?? [];

        return new LabelData(
            labelId: $data['id'] ?? '',
            status: 'purchased',
            trackingNumber: $data['tracking_code'] ?? '',
            labelUrl: $postageLabel['label_url'] ?? '',
            cost: (float) ($cheapestRate['rate'] ?? 0),
            currency: $cheapestRate['currency'] ?? 'USD',
            carrierCode: $cheapestRate['carrier'] ?? null,
            serviceCode: $cheapestRate['service'] ?? null,
            shipmentId: $shipmentId,
            createdAt: $data['created_at'] ?? null,
            raw: $data,
        );
    }

    /**
     * Track a shipment via GET /v2/trackers/{id} or POST /v2/trackers.
     *
     * @see https://docs.easypost.com/docs/trackers
     */
    public function track(string $trackingNumber, ?string $carrierId = null): TrackingData
    {
        $response = $this->http()->post('/v2/trackers', array_filter([
            'tracker' => array_filter([
                'tracking_code' => $trackingNumber,
                'carrier'       => $carrierId,
            ], fn ($v) => $v !== null),
        ]));

        $this->throwIfFailed($response, 'track shipment');

        $data = $response->json();

        $events = array_map(fn (array $e) => [
            'occurred_at' => $e['datetime'] ?? null,
            'description' => $e['message'] ?? '',
            'city'        => $e['tracking_location']['city'] ?? null,
            'state'       => $e['tracking_location']['state'] ?? null,
            'country'     => $e['tracking_location']['country'] ?? null,
        ], $data['tracking_details'] ?? []);

        return new TrackingData(
            trackingNumber: $data['tracking_code'] ?? $trackingNumber,
            status: $data['status'] ?? 'unknown',
            statusDescription: $data['status_detail'] ?? '',
            carrierId: $carrierId,
            carrierCode: $data['carrier'] ?? null,
            estimatedDeliveryDate: $data['est_delivery_date'] ?? null,
            events: $events,
            raw: $data,
        );
    }

    /**
     * EasyPost does not support label refunds via the REST API in the same way.
     * Refunds can be requested through the EasyPost dashboard.
     *
     * @throws ProviderException
     */
    public function refundLabel(string $labelId): bool
    {
        $response = $this->http()->post("/v2/shipments/{$labelId}/refund");

        $this->throwIfFailed($response, 'refund label');

        $data = $response->json();

        return in_array($data['refund_status'] ?? '', ['submitted', 'refunded'], true);
    }

    /**
     * EasyPost does not have a public carrier listing endpoint.
     * Returns carrier accounts connected to the account.
     *
     * @see https://docs.easypost.com/docs/carrier-accounts
     */
    public function listCarriers(): array
    {
        $response = $this->http()->get('/v2/carrier_accounts');

        $this->throwIfFailed($response, 'list carriers');

        return $response->json() ?? [];
    }

    private function buildShipmentPayload(ShipmentData $shipment): array
    {
        return [
            'shipment' => [
                'to_address'   => $this->mapAddress($shipment->shipTo),
                'from_address' => $this->mapAddress($shipment->shipFrom),
                'parcel'       => $this->mapParcel($shipment),
            ],
        ];
    }

    private function mapAddress(AddressData $address): array
    {
        return array_filter([
            'name'    => $address->name,
            'company' => $address->company,
            'street1' => $address->street1,
            'street2' => $address->street2,
            'city'    => $address->city,
            'state'   => $address->state,
            'zip'     => $address->postalCode,
            'country' => $address->country,
            'phone'   => $address->phone,
            'email'   => $address->email,
        ], fn ($v) => $v !== null);
    }

    private function mapParcel(ShipmentData $shipment): array
    {
        return [
            'length' => $shipment->parcel->length,
            'width'  => $shipment->parcel->width,
            'height' => $shipment->parcel->height,
            'weight' => $shipment->parcel->weight,
        ];
    }

    protected function extractErrorMessage(array $body): string
    {
        $error = $body['error'] ?? null;

        if (is_array($error)) {
            return $error['message'] ?? implode('; ', $error['errors'] ?? []);
        }

        return $error ?? $body['message'] ?? '';
    }
}
