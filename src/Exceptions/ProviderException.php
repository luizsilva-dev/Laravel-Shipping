<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Exceptions;

class ProviderException extends ShippingException
{
    public function __construct(
        string $provider,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        array $context = [],
    ) {
        parent::__construct(
            message: "[{$provider}] {$message}",
            code: $code,
            previous: $previous,
            context: $context,
        );
    }

    public static function unsupportedFeature(string $provider, string $feature): self
    {
        return new self(
            provider: $provider,
            message: "The feature \"{$feature}\" is not supported by this provider.",
            code: 501,
        );
    }

    public static function apiError(string $provider, string $message, int $statusCode = 0, array $context = []): self
    {
        return new self(
            provider: $provider,
            message: $message,
            code: $statusCode,
            context: $context,
        );
    }
}
