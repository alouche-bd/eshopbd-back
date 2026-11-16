<?php

namespace App\Services\Galaxy;

class GalaxyAccountAmbiguousException extends GalaxyApiException
{
    public function isAccountAmbiguous(): bool
    {
        return true;
    }

    public function accountCount(): ?int
    {
        $accounts = $this->getDetails()['accounts'] ?? null;

        return is_int($accounts) ? $accounts : null;
    }
}
