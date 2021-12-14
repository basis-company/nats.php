<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Unsubscribe extends Prototype
{
    public string $sid;

    public function __toString()
    {
        return "UNSUB $this->sid";
    }
}
