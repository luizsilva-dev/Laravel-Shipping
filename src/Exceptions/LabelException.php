<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Exceptions;

class LabelException extends ShippingException
{
    public function __construct(
        string $message = 'Failed to create shipping label.',
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
