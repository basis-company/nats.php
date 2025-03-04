<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use DomainException;

final class ConsumerLimits
{
    public const MAX_ACK_PENDING = 'max_ack_pending';
    public const INACTIVE_THRESHOLD = 'inactive_threshold';


    public static function validate(array $limits): array
    {
        foreach ($limits as $param => $value) {
            if (!self::paramIsValid($param, $value)) {
                throw new DomainException("Invalid param: $param");
            }
        }

        return $limits;
    }

    private static function paramIsValid(string $param, $value): bool
    {

        if ($param === self::MAX_ACK_PENDING) {
            return is_int($value);
        }

        if ($param === self::INACTIVE_THRESHOLD) {
            return is_int($value);
        }

        return true;
    }

}
