<?php

namespace App\Services\Galaxy;

final class GalaxyApiMode
{
    public const LEGACY = 'legacy';
    public const NEW = 'new';

    public static function resolve($value): string
    {
        return is_string($value) && strtolower(trim($value)) === self::NEW ? self::NEW : self::LEGACY;
    }

    public static function isNew($value): bool
    {
        return self::resolve($value) === self::NEW;
    }
}
