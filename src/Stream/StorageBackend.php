<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use DomainException;

abstract class StorageBackend
{
    public const FILE = 'file';
    public const MEMORY = 'memory';

    public static function validate(string $storage): string
    {
        if (!self::isValid($storage)) {
            throw new DomainException("Invalid storage backend: $storage");
        }

        return $storage;
    }

    public static function isValid(string $storage): bool
    {
        return in_array($storage, [self::FILE, self::MEMORY]);
    }
}
