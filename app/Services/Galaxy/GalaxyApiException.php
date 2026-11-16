<?php

namespace App\Services\Galaxy;

use RuntimeException;
use Throwable;

class GalaxyApiException extends RuntimeException
{
    public const PARTNER_CLIENT_UNKNOWN = 'PARTNER_CLIENT_UNKNOWN';
    public const PARTNER_SCOPE_DENIED = 'PARTNER_SCOPE_DENIED';
    public const PARTNER_USER_NOT_FOUND = 'PARTNER_USER_NOT_FOUND';
    public const PARTNER_USER_INACTIVE = 'PARTNER_USER_INACTIVE';
    public const ACCOUNT_AMBIGUOUS = 'ACCOUNT_AMBIGUOUS';
    public const ACCOUNT_NOT_MEMBER = 'ACCOUNT_NOT_MEMBER';

    private int $statusCode;
    private array $errors;
    private ?string $errorCode;
    private array $details;

    public function __construct(
        string $message,
        int $statusCode = 0,
        array $errors = [],
        ?Throwable $previous = null,
        ?string $errorCode = null,
        array $details = []
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public static function fromErrorBody(int $statusCode, string $message, ?string $errorCode, array $errors = [], array $details = []): self
    {
        if ($statusCode === 409 && $errorCode === self::ACCOUNT_AMBIGUOUS) {
            return new GalaxyAccountAmbiguousException($message, $statusCode, $errors, null, $errorCode, $details);
        }

        if ($statusCode === 403 && in_array($errorCode, [self::PARTNER_CLIENT_UNKNOWN, self::PARTNER_SCOPE_DENIED], true)) {
            return new GalaxyPartnerAuthorizationException($message, $statusCode, $errors, null, $errorCode, $details);
        }

        return new self($message, $statusCode, $errors, null, $errorCode, $details);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    public function isConflict(): bool
    {
        return $this->statusCode === 409;
    }

    public function isPartnerAuthorizationError(): bool
    {
        return false;
    }

    public function isAccountAmbiguous(): bool
    {
        return false;
    }

    public function isAccountNotMember(): bool
    {
        return $this->statusCode === 403 && $this->errorCode === self::ACCOUNT_NOT_MEMBER;
    }

    public function isUserInactive(): bool
    {
        return $this->statusCode === 403 && $this->errorCode === self::PARTNER_USER_INACTIVE;
    }

    public function isUnauthorized(): bool
    {
        if (in_array($this->statusCode, [400, 401], true)) {
            return true;
        }

        return $this->statusCode === 403
            && !$this->isPartnerAuthorizationError()
            && !$this->isAccountNotMember()
            && !$this->isUserInactive();
    }
}
