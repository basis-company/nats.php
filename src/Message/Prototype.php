<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use InvalidArgumentException;

abstract class Prototype
{
    abstract public function render(): string;

    public static function create(string $data): self
    {
        // @phan-suppress-next-line PhanTypeInstantiateAbstractStatic
        return new static(Payload::parse($data));
    }

    public function __construct(array|Payload|null $payload = null)
    {
        if ($payload === null) {
            return;
        }

        $values = is_array($payload) ? $payload : $payload->getValues();
        if ($values === null) {
            return;
        }

        foreach ($values as $k => $v) {
            if (!property_exists($this, $k)) {
                throw new InvalidArgumentException("Invalid property $k for message " . get_class($this));
            }
            $this->$k = $v;
        }
    }
}
