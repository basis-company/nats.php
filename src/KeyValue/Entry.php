<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

class Entry
{
    public function __construct(
        public readonly string $bucket,
        public readonly string $key,
        public readonly mixed $value,
        public readonly int $revision,
        public readonly string $time = "",
    ) {
    }
}
