<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Msg extends Prototype
{
    public string $length;
    public string $payload = '';
    public ?string $replyTo = null;
    public string $sid;
    public string $subject;

    public static function create(string $data): self
    {
        $args = explode(' ', $data);
        $values = [];

        switch (count($args)) {
            case 3:
                $values = array_combine(['subject', 'sid', 'length'], $args);
                break;

            case 4:
                $values = array_combine(['subject', 'sid', 'replyTo', 'length'], $args);
                break;

            default:
                throw new Exception("Invalid Msg: $data");
        }

        return new self($values);
    }

    public function __toString()
    {
        return 'MSG ' . json_encode($this);
    }
}
