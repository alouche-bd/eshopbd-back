<?php

namespace App\Services\Galaxy;

use RuntimeException;
use Throwable;

class GalaxyApiException extends RuntimeException
{
    private int $statusCode;
    private array $errors;

    public function __construct(string $message, int $statusCode = 0, array $errors = [], ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isConflict(): bool
    {
        return $this->statusCode === 409;
    }

    public function isUnauthorized(): bool
    {
        return in_array($this->statusCode, [400, 401, 403], true);
    }
}
