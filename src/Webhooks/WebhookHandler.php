<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Webhooks;

use Illuminate\Http\Request;
use LuizSilvaDev\LaravelShipping\Exceptions\ShippingException;

/**
 * Generic webhook handler structure.
 *
 * Each provider has different webhook event shapes. Extend this class
 * per provider or use the static factory methods to parse incoming payloads.
 *
 * Supported providers and their event types:
 *
 * ShipStation:
 *   - tracking_update, batch_created, batch_completed, rate_fetched
 *
 * Shippo:
 *   - track_updated, batch_created, transaction_created
 *
 * EasyPost:
 *   - tracker.created, tracker.updated, shipment.created
 */
class WebhookHandler
{
    public function __construct(
        protected readonly string $provider,
    ) {}

    /**
     * Parse a raw webhook payload from a PSR-7 / Laravel request.
     *
     * @return array{event: string, provider: string, payload: array}
     *
     * @throws ShippingException
     */
    public function handle(Request $request): array
    {
        $payload = $request->json()->all();

        if (empty($payload)) {
            throw new ShippingException("Empty or invalid webhook payload from [{$this->provider}].");
        }

        return [
            'event'    => $this->resolveEvent($payload),
            'provider' => $this->provider,
            'payload'  => $payload,
        ];
    }

    /**
     * Verify webhook authenticity using a shared secret (HMAC-SHA256).
     */
    public function verify(Request $request, string $secret): bool
    {
        $signature = $request->header('X-Shipstation-Signature')
            ?? $request->header('Shippo-Signature')
            ?? $request->header('X-Hmac-Signature-256')
            ?? '';

        if (empty($signature)) {
            return false;
        }

        $computed = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($computed, ltrim($signature, 'sha256='));
    }

    private function resolveEvent(array $payload): string
    {
        return match ($this->provider) {
            'shipstation' => $payload['resource_type'] ?? $payload['event'] ?? 'unknown',
            'shippo'      => $payload['event'] ?? 'unknown',
            'easypost'    => $payload['description'] ?? $payload['type'] ?? 'unknown',
            default       => $payload['event'] ?? 'unknown',
        };
    }
}
