<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use DomainException;

abstract class RetentionPolicy
{
    public const INTEREST = 'interest';
    public const LIMITS = 'limits';
    public const WORK_QUEUE = 'workqueue';

    public static function validate(string $policy): string
    {
        if (!self::isValid($policy)) {
            throw new DomainException("Invalid retention policy: $policy");
        }

        return $policy;
    }

    public static function isValid(string $policy): bool
    {
        return in_array($policy, [self::LIMITS, self::INTEREST, self::WORK_QUEUE]);
    }
}
