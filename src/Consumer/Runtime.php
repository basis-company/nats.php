<?php

namespace Basis\Nats\Consumer;

class Runtime
{
    public $processed ;
    public $empty;
    public function __construct(
        int $processed = 0,
        bool $empty = false
    ) {
        $this->processed = $processed;
        $this->empty = $empty;
    }
}
