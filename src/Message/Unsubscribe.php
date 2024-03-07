<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Unsubscribe extends Prototype
{
    public $sid;

    public function render(): string
    {
        return "UNSUB $this->sid";
    }
}
