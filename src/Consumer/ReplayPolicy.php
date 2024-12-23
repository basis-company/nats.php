<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use DomainException;

abstract class ReplayPolicy
{
    public const INSTANT = 'instant';
    public const ORIGINAL = 'original';

    public static function validate(string $policy): string
    {
        if (!self::isValid($policy)) {
            throw new DomainException("Invalid replay policy: $policy");
        }

        return $policy;
    }

    public static function isValid(string $policy): bool
    {
        return in_array($policy, [self::INSTANT, self::ORIGINAL]);
    }
}
