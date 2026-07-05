<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Exceptions;

class InvalidAddressException extends ShippingException
{
    public function __construct(
        string $message = 'The provided address is invalid.',
        array $context = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            message: $message,
            code: 422,
            previous: $previous,
            context: $context,
        );
    }
}
