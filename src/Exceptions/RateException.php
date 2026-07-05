<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Exceptions;

class RateException extends ShippingException
{
    public function __construct(
        string $message = 'Failed to retrieve shipping rates.',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: 0,
            previous: $previous,
            context: $context,
        );
    }
}
