<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class RealtimeDatabaseUnavailable extends RuntimeException
{
    /** @param array<string, mixed> $diagnostics */
    public function __construct(
        string $message,
        private readonly array $diagnostics,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->diagnostics;
    }
}
