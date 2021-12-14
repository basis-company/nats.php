<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Publish extends Prototype
{
    public string $subject;
    public string $payload;
    public ?string $replyTo = null;

    public function __toString()
    {
        $args = ['PUB', $this->subject];

        if ($this->replyTo) {
            $args[] = $this->replyTo;
        }

        $args[] = strlen($this->payload) . "\r\n" . $this->payload;

        return implode(' ', $args);
    }
}
