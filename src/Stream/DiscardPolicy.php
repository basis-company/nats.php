<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use DomainException;

abstract class DiscardPolicy
{
    public const OLD = 'old';
    public const NEW = 'new';

    public static function validate(string $policy): string
    {
        if (!self::isValid($policy)) {
            throw new DomainException("Invalid discard policy: $policy");
        }

        return $policy;
    }

    public static function isValid(string $policy): bool
    {
        return in_array($policy, [self::OLD, self::NEW]);
    }
}
