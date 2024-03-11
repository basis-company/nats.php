<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Ok extends Prototype
{
    public function render(): string
    {
        return "+OK";
    }
}
