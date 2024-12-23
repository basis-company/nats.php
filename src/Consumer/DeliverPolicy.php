<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use DomainException;

abstract class DeliverPolicy
{
    public const ALL = 'all';
    public const BY_START_SEQUENCE = 'by_start_sequence';
    public const BY_START_TIME = 'by_start_time';
    public const LAST = 'last';
    public const LAST_PER_SUBJECT = 'last_per_subject';
    public const NEW = 'new';

    public static function validate(string $policy): string
    {
        if (!self::isValid($policy)) {
            throw new DomainException("Invalid deliver policy: $policy");
        }

        return $policy;
    }

    public static function isValid(string $policy): bool
    {
        return in_array($policy, [
            self::ALL,
            self::BY_START_SEQUENCE,
            self::BY_START_TIME,
            self::LAST,
            self::LAST_PER_SUBJECT,
            self::NEW,
        ]);
    }
}
