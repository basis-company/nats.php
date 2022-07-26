<?php

namespace Basis\Nats\Consumer;

class Runtime
{
    public function __construct(
        public int $processed = 0,
        public bool $empty = false,
    ) {
    }
}
