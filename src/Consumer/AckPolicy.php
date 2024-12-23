<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use DomainException;

abstract class AckPolicy
{
    public const ALL = 'all';
    public const EXPLICIT = 'explicit';
    public const NONE = 'none';

    public static function validate(string $policy): string
    {
        if (!self::isValid($policy)) {
            throw new DomainException("Invalid ack policy: $policy");
        }

        return $policy;
    }

    public static function isValid(string $policy): bool
    {
        return in_array($policy, [self::EXPLICIT, self::NONE, self::ALL]);
    }
}
