<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Subscribe extends Prototype
{
    public $sid;
    public $subject;
    public $group = null;

    public function render(): string
    {
        $args = ['SUB', $this->subject];

        if ($this->group) {
            $args[] = $this->group;
        }

        $args[] = $this->sid;

        return implode(' ', $args);
    }
}
