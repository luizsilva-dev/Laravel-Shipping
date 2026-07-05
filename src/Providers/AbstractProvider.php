<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Providers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use LuizSilvaDev\LaravelShipping\Contracts\ShippingProviderInterface;
use LuizSilvaDev\LaravelShipping\Exceptions\ProviderException;

abstract class AbstractProvider implements ShippingProviderInterface
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    abstract protected function providerName(): string;

    protected function baseUrl(): string
    {
        return rtrim($this->config['base_url'] ?? '', '/');
    }

    protected function http(): PendingRequest
    {
        return $this->buildHttpClient();
    }

    abstract protected function buildHttpClient(): PendingRequest;

    protected function throwIfFailed(Response $response, string $action): void
    {
        if ($response->failed()) {
            $body = $response->json() ?? [];
            $message = $this->extractErrorMessage($body) ?: $response->body();

            throw ProviderException::apiError(
                provider: $this->providerName(),
                message: "Failed to {$action}: {$message}",
                statusCode: $response->status(),
                context: $body,
            );
        }
    }

    protected function extractErrorMessage(array $body): string
    {
        return $body['message']
            ?? $body['error']
            ?? $body['errors'][0]['message']
            ?? $body['errors'][0]
            ?? '';
    }
}
