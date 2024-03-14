<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Ack extends Prototype
{
    public string $subject;
    public string $command = '+ACK';

    public ?Payload $payload = null;

    public function render(): string
    {
        $payload = ($this->payload ?: Payload::parse(''))->render();
        return "PUB $this->subject $this->command $payload";
    }
}
