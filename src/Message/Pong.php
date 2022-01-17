<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Pong extends Prototype
{
    public function render(): string
    {
        return "PONG";
    }
}
