<?php

namespace App\Services\Galaxy;

final class GalaxyPartnerUser
{
    public const ROLES = ['PRACTITIONER', 'LAB', 'PATIENT', 'MARKETING', 'SUPER_ADMIN', 'ADV', 'REGULATORY_AFFAIRS'];

    public function __construct(
        public readonly string $galaxyUuid,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly ?string $role,
        public readonly string $language,
        public readonly ?string $billingCountry,
        public readonly bool $isActive
    ) {
    }

    public static function fromArray(array $data): self
    {
        foreach (['galaxyUuid', 'email'] as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || $data[$field] === '') {
                throw self::invalid($field);
            }
        }
        if (!array_key_exists('isActive', $data) || !is_bool($data['isActive'])) {
            throw self::invalid('isActive');
        }

        $role = $data['role'] ?? null;

        return new self(
            $data['galaxyUuid'],
            $data['email'],
            is_string($data['firstName'] ?? null) ? $data['firstName'] : '',
            is_string($data['lastName'] ?? null) ? $data['lastName'] : '',
            is_string($role) && in_array($role, self::ROLES, true) ? $role : null,
            is_string($data['language'] ?? null) ? $data['language'] : '',
            is_string($data['billingCountry'] ?? null) ? $data['billingCountry'] : null,
            $data['isActive']
        );
    }

    private static function invalid(string $field): GalaxyApiException
    {
        return new GalaxyApiException('galaxy-api PartnerUserDto has no valid ' . $field, 502);
    }
}
