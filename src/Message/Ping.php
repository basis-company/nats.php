<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Ping extends Prototype
{
    public function render(): string
    {
        return "PING";
    }
}
