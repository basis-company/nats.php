<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Progress extends Prototype
{
    public string $subject;

    public function render(): string
    {
        $payload = Payload::parse('+WPI')->render();
        return "PUB $this->subject  $payload";
    }
}
