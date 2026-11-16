<?php

namespace App\Services\Galaxy;

class GalaxyPartnerAuthorizationException extends GalaxyApiException
{
    public function isPartnerAuthorizationError(): bool
    {
        return true;
    }

    public function isClientUnknown(): bool
    {
        return $this->getErrorCode() === self::PARTNER_CLIENT_UNKNOWN;
    }

    public function missingScopes(): array
    {
        $missing = $this->getDetails()['missing'] ?? [];

        return is_array($missing) ? array_values(array_filter($missing, 'is_string')) : [];
    }
}
