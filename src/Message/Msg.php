<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use Exception;

class Msg extends Prototype
{
    public ?int $hlength = null;
    public ?string $replyTo = null;
    public int $length;
    public Payload $payload;
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
                if (is_numeric($values['sid'])) {
                    $values = array_combine(['subject', 'sid', 'hlength', 'length'], $args);
                }
                break;

            case 5:
                $values = array_combine(['subject', 'sid', 'replyTo', 'hlength', 'length'], $args);
                break;

            default:
                throw new Exception("Invalid Msg: $data");
        }

        foreach (['length', 'hlength'] as $key) {
            if (array_key_exists($key, $values)) {
                $values[$key] = (int) $values[$key];
            }
        }

        return new self($values);
    }

    public function __toString(): string
    {
        return $this->payload->body;
    }

    public function parse($payload): self
    {
        $headers = [];
        if ($this->hlength) {
            $rawHeaders = substr($payload, 0, $this->hlength);
            $payload = substr($payload, $this->hlength);
        }
        $this->payload = new Payload($payload, $headers);
        return $this;
    }

    public function render(): string
    {
        return 'MSG ' . json_encode($this);
    }
}
