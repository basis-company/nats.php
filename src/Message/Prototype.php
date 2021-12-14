<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use InvalidArgumentException;

abstract class Prototype
{
    abstract public function __toString();

    public static function create(string $data): self
    {
        return new static(json_decode($data, true) ?: []);
    }

    public function __construct(array $values = [])
    {
        foreach ($values as $k => $v) {
            if (!property_exists($this, $k)) {
                throw new InvalidArgumentException("Invalid property $k for message " . get_class($this));
            }
            $this->$k = $v;
        }
    }
}
