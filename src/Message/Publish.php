<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Publish extends Prototype
{
    public $subject;
    public $payload;
    public $replyTo = null;

    public function render(): string
    {
        $args = ['PUB'];
        if ($this->payload->hasHeaders()) {
            $args = ['HPUB'];
        }

        $args[] = $this->subject;

        if ($this->replyTo) {
            $args[] = $this->replyTo;
        }

        $args[] = $this->payload->render();

        return implode(' ', $args);
    }
}
