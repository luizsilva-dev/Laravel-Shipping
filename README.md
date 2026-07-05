# Laravel Shipping

[![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-11%2B-red)](https://laravel.com)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A modern Laravel package that provides a **unified shipping abstraction** for multiple shipping carriers. Inspired by Laravel's `Storage` and `Mail` systems — one clean API, multiple providers.

---

## Supported Providers

| Provider | Rates | Labels | Tracking | Address Validation | Refund | Carriers |
|---|---|---|---|---|---|---|
| **ShipStation** (API v2) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Shippo** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **EasyPost** | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## Requirements

- PHP 8.2+
- Laravel 11+

---

## Installation

```bash
composer require luizsilva-dev/laravel-shipping
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=laravel-shipping-config
```

---

## Configuration

Add the following variables to your `.env` file:

```env
SHIPPING_DRIVER=shipstation

# ShipStation API v2
SHIPSTATION_API_KEY=your-shipstation-api-key
SHIPSTATION_SANDBOX=true

# Shippo
SHIPPO_API_KEY=your-shippo-api-key
SHIPPO_SANDBOX=true

# EasyPost
EASYPOST_API_KEY=your-easypost-api-key
EASYPOST_SANDBOX=true
```

The published `config/shipping.php`:

```php
return [
    'default' => env('SHIPPING_DRIVER', 'shipstation'),

    'drivers' => [
        'shipstation' => [
            'driver'   => 'shipstation',
            'api_key'  => env('SHIPSTATION_API_KEY'),
            'sandbox'  => env('SHIPSTATION_SANDBOX', true),
            'base_url' => env('SHIPSTATION_BASE_URL', 'https://api.shipstation.com'),
        ],

        'shippo' => [
            'driver'   => 'shippo',
            'api_key'  => env('SHIPPO_API_KEY'),
            'sandbox'  => env('SHIPPO_SANDBOX', true),
            'base_url' => 'https://api.goshippo.com',
        ],

        'easypost' => [
            'driver'   => 'easypost',
            'api_key'  => env('EASYPOST_API_KEY'),
            'sandbox'  => env('EASYPOST_SANDBOX', true),
            'base_url' => 'https://api.easypost.com',
        ],
    ],
];
```

> **Sandbox mode:** When `sandbox=true`, ShipStation routes to `https://api-stage.shipstation.com`. Shippo and EasyPost use their test API keys natively (no separate URL needed — use a test key).

---

## Basic Usage

The package registers a `Shipping` facade. You can call any provider using `driver()`:

```php
use LuizSilvaDev\LaravelShipping\Facades\Shipping;

// Use the default driver (from config)
Shipping::rates($shipment);

// Or specify a driver explicitly
Shipping::driver('shipstation')->rates($shipment);
Shipping::driver('shippo')->rates($shipment);
Shipping::driver('easypost')->rates($shipment);
```

---

## DTOs

All methods accept and return strongly-typed DTOs.

### AddressData

```php
use LuizSilvaDev\LaravelShipping\DTOs\AddressData;

$address = new AddressData(
    name: 'John Doe',
    street1: '1600 Pennsylvania Avenue NW',
    city: 'Washington',
    state: 'DC',
    postalCode: '20500',
    country: 'US',
    company: 'ACME Corp',       // optional
    street2: 'Suite 100',       // optional
    phone: '2025551234',        // optional
    email: 'john@example.com',  // optional
    isResidential: false,       // optional
);

// Or from array
$address = AddressData::fromArray([
    'name'        => 'John Doe',
    'street1'     => '1600 Pennsylvania Avenue NW',
    'city'        => 'Washington',
    'state'       => 'DC',
    'postal_code' => '20500',  // also accepts 'zip'
    'country'     => 'US',
]);
```

### ParcelData

```php
use LuizSilvaDev\LaravelShipping\DTOs\ParcelData;

$parcel = new ParcelData(
    weight: 6.0,
    length: 10.0,
    width: 8.0,
    height: 4.0,
    weightUnit: 'ounce',   // 'ounce', 'pound', 'gram', 'kilogram'
    dimensionUnit: 'inch', // 'inch', 'centimeter'
);
```

### ShipmentData

```php
use LuizSilvaDev\LaravelShipping\DTOs\ShipmentData;

$shipment = new ShipmentData(
    shipFrom: $fromAddress,
    shipTo: $toAddress,
    parcel: $parcel,
    carrierId: 'se-123890',         // optional: filter by carrier
    serviceCode: 'usps_priority_mail', // optional: filter by service
);
```

---

## Address Validation

```php
use LuizSilvaDev\LaravelShipping\Facades\Shipping;
use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\Exceptions\InvalidAddressException;

$address = new AddressData(
    name: 'John Doe',
    street1: '1600 Pennsylvania Avenue NW',
    city: 'Washington',
    state: 'DC',
    postalCode: '20500',
    country: 'US',
);

try {
    $validated = Shipping::driver('shipstation')->validateAddress($address);

    echo $validated->street1;    // normalized address
    echo $validated->postalCode; // may return ZIP+4 (e.g. 20500-0003)
} catch (InvalidAddressException $e) {
    echo 'Invalid address: ' . $e->getMessage();
}
```

---

## Get Shipping Rates

```php
use LuizSilvaDev\LaravelShipping\Facades\Shipping;
use LuizSilvaDev\LaravelShipping\DTOs\AddressData;
use LuizSilvaDev\LaravelShipping\DTOs\ParcelData;
use LuizSilvaDev\LaravelShipping\DTOs\ShipmentData;
use LuizSilvaDev\LaravelShipping\Exceptions\RateException;

$shipment = new ShipmentData(
    shipFrom: new AddressData(
        name: 'Warehouse',
        street1: '4301 Bull Creek Road',
        city: 'Austin',
        state: 'TX',
        postalCode: '78731',
        country: 'US',
    ),
    shipTo: new AddressData(
        name: 'Customer',
        street1: '179 N Harbor Dr',
        city: 'Redondo Beach',
        state: 'CA',
        postalCode: '90277',
        country: 'US',
    ),
    parcel: new ParcelData(
        weight: 6.0,
        length: 10.0,
        width: 8.0,
        height: 4.0,
    ),
    carrierId: 'se-123890', // optional
);

try {
    $rates = Shipping::driver('shipstation')->rates($shipment);

    foreach ($rates as $rate) {
        echo "{$rate->serviceType}: \${$rate->amount} ({$rate->deliveryDays} days)\n";
    }
} catch (RateException $e) {
    echo 'No rates available: ' . $e->getMessage();
}
```

Each `RateData` contains:

| Property | Type | Description |
|---|---|---|
| `rateId` | `string` | Provider rate ID (use to buy label) |
| `carrierId` | `string` | Carrier account ID |
| `carrierCode` | `string` | Carrier code (e.g. `usps`, `ups`) |
| `serviceCode` | `string` | Service code (e.g. `usps_priority_mail`) |
| `serviceType` | `string` | Human-readable service name |
| `amount` | `float` | Shipping cost |
| `currency` | `string` | Currency code (e.g. `USD`) |
| `deliveryDays` | `?int` | Estimated delivery days |
| `estimatedDeliveryDate` | `?string` | Estimated delivery date (ISO 8601) |

---

## Create Label

```php
use LuizSilvaDev\LaravelShipping\Facades\Shipping;
use LuizSilvaDev\LaravelShipping\Exceptions\LabelException;

try {
    $label = Shipping::driver('shipstation')->createLabel($shipment);

    echo $label->labelId;        // provider label ID
    echo $label->trackingNumber; // tracking number
    echo $label->labelUrl;       // PDF/PNG download URL
    echo $label->cost;           // shipping cost paid
} catch (LabelException $e) {
    echo 'Label creation failed: ' . $e->getMessage();
}
```

Each `LabelData` contains:

| Property | Type | Description |
|---|---|---|
| `labelId` | `string` | Provider label ID |
| `status` | `string` | Label status |
| `trackingNumber` | `string` | Tracking number |
| `labelUrl` | `string` | Label download URL |
| `cost` | `float` | Amount charged |
| `currency` | `string` | Currency code |
| `carrierId` | `?string` | Carrier account ID |
| `carrierCode` | `?string` | Carrier code |
| `serviceCode` | `?string` | Service code |
| `shipmentId` | `?string` | Shipment ID |
| `createdAt` | `?string` | Creation timestamp |

---

## Track Shipment

```php
use LuizSilvaDev\LaravelShipping\Facades\Shipping;

// ShipStation: pass the label_id
$tracking = Shipping::driver('shipstation')->track('se-label-xyz789');

// Shippo: pass tracking number + carrier code
$tracking = Shipping::driver('shippo')->track('1Z999AA10123456784', 'ups');

// EasyPost: pass tracking number + optional carrier
$tracking = Shipping::driver('easypost')->track('1Z999AA10123456784', 'UPS');

echo $tracking->status;              // e.g. 'DE' (Delivered)
echo $tracking->statusDescription;  // e.g. 'Delivered'
echo $tracking->estimatedDeliveryDate;

foreach ($tracking->events as $event) {
    echo "{$event['occurred_at']}: {$event['description']} ({$event['city']}, {$event['state']})\n";
}
```

---

## Refund / Void a Label

```php
$voided = Shipping::driver('shipstation')->refundLabel('se-label-xyz789');

if ($voided) {
    echo 'Label voided successfully.';
}
```

---

## List Carriers

```php
$carriers = Shipping::driver('shipstation')->listCarriers();

foreach ($carriers as $carrier) {
    echo "{$carrier['carrier_code']}: {$carrier['friendly_name']}\n";
}
```

---

## Webhook Handling

```php
use LuizSilvaDev\LaravelShipping\Webhooks\WebhookHandler;

// In your controller
public function handleShipStation(Request $request): JsonResponse
{
    $handler = new WebhookHandler('shipstation');

    // Optional: verify HMAC signature
    if (! $handler->verify($request, config('shipping.drivers.shipstation.webhook_secret'))) {
        abort(401, 'Invalid webhook signature.');
    }

    $event = $handler->handle($request);

    // $event['event']   => event type string
    // $event['payload'] => raw payload array

    return response()->json(['received' => true]);
}
```

---

## Exception Handling

| Exception | When thrown |
|---|---|
| `ShippingException` | Base exception for all shipping errors |
| `ProviderException` | API-level error from a provider |
| `InvalidAddressException` | Address validation failed |
| `RateException` | No rates returned or rate fetch failed |
| `LabelException` | Label creation failed |

```php
use LuizSilvaDev\LaravelShipping\Exceptions\ProviderException;
use LuizSilvaDev\LaravelShipping\Exceptions\ShippingException;

try {
    $label = Shipping::driver('shipstation')->createLabel($shipment);
} catch (ProviderException $e) {
    // Provider-specific error with status code
    logger()->error($e->getMessage(), $e->getContext());
} catch (ShippingException $e) {
    // General shipping error
    logger()->error($e->getMessage());
}
```

---

## Adding a New Provider

1. Create `src/Providers/MyCarrierProvider.php` extending `AbstractProvider`
2. Implement all methods from `ShippingProviderInterface`
3. Register in `ShippingManager`:

```php
protected function createMycarrierDriver(): ShippingProviderInterface
{
    return new MyCarrierProvider($this->getDriverConfig('mycarrier'));
}
```

4. Add config entry in `config/shipping.php`:

```php
'mycarrier' => [
    'driver'  => 'mycarrier',
    'api_key' => env('MYCARRIER_API_KEY'),
    'sandbox' => env('MYCARRIER_SANDBOX', true),
    'base_url' => 'https://api.mycarrier.com',
],
```

5. Use it:

```php
Shipping::driver('mycarrier')->rates($shipment);
```

---

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

---

## Provider Notes

### ShipStation (API v2)
- Auth: `API-Key` header
- Sandbox URL: `https://api-stage.shipstation.com`
- Tracking uses `label_id` (not raw tracking number) via `GET /v2/labels/{label_id}/track`
- Address validation: `POST /v2/addresses/validate`
- `createLabel()` without `serviceCode` in `ShipmentData` **automatically selects the cheapest available rate**
- Docs: https://docs.shipstation.com

### Shippo
- Auth: `ShippoToken <token>` header
- Use a **test token** (starts with `shippo_test_`) for sandbox — no separate URL
- Tracking: `GET /tracks/{carrier}/{tracking_number}`
- **`track()` requires `$carrierId`** as the second argument (e.g. `'ups'`, `'usps'`, `'fedex'`)
- `createLabel()` without `serviceCode` **automatically selects the cheapest available rate**
- Docs: https://docs.goshippo.com

### EasyPost
- Auth: HTTP Basic Auth (API key as username, empty password)
- Use an **EZT (test) API key** for sandbox — no separate URL
- **Label creation requires two API calls** internally (create shipment → buy label) — this is handled transparently by the package
- `createLabel()` without `serviceCode` **automatically selects the cheapest available rate**
- Docs: https://docs.easypost.com

---

## License

MIT — see [LICENSE](LICENSE).
