<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Nak extends Prototype
{
    public string $subject;
    public float $delay;

    public function render(): string
    {
        $data = ['-NAK'];
        if (isset($this->delay) && $this->delay > 0) {
            $data[] = json_encode(['delay' => $this->delay * 10 ** 9]);
        }
        $payload = Payload::parse(implode(' ', $data))->render();
        return "PUB $this->subject  $payload";
    }
}
