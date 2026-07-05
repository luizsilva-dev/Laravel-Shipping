<?php

declare(strict_types=1);

namespace LuizSilvaDev\LaravelShipping\Exceptions;

use RuntimeException;

class ShippingException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        protected readonly array $context = [],
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
