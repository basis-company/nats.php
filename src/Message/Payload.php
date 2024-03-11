<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Payload
{
    public static function parse(mixed $data): self
    {
        if ($data instanceof self) {
            return $data;
        }
        if (is_string($data)) {
            return new self($data);
        }
        if (is_array($data)) {
            return new self(json_encode($data));
        }

        return new self('');
    }

    public function __construct(
        public string $body,
        public array $headers = [],
        public ?string $subject = null,
        public ?int $timestampNanos = null
    ) {
        $hdrs = $this->getValue('message.hdrs');
        if ($hdrs) {
            foreach (explode("\r\n", base64_decode($hdrs)) as $line) {
                if (strpos($line, ':') === false) {
                    continue;
                }
                [$key, $value] = explode(':', $line, 2);
                $this->headers[trim($key)] = trim($value);
            }
        }
    }

    public function __get(string $name): mixed
    {
        $values = $this->getValues();

        if (is_object($values) && property_exists($values, $name)) {
            return $values->$name;
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->body;
    }

    public function hasHeader(string $key)
    {
        return array_key_exists($key, $this->headers);
    }

    public function hasHeaders(): bool
    {
        return count($this->headers) > 0;
    }

    public function getHeader(string $key)
    {
        return $this->hasHeader($key) ? $this->headers[$key] : null;
    }

    public function getValues()
    {
        return json_decode($this->body);
    }

    public function getValue(string $key)
    {
        $values = (object) $this->getValues() ?: [];

        foreach (explode('.', $key) as $property) {
            if (!is_object($values)) {
                return;
            }
            if (!property_exists($values, $property)) {
                return;
            }
            $values = $values->$property;
        }
        if ($key && $values) {
            return $values;
        }
    }

    public function isEmpty(): bool
    {
        return $this->body == '';
    }

    public function render(): string
    {
        if (count($this->headers)) {
            $headers = "NATS/1.0\r\n";
            foreach ($this->headers as $k => $v) {
                $headers .= "$k: $v\r\n";
            }
            $headers .= "\r\n";

            $crc = strlen($headers) . ' ' . strlen($headers . $this->body);

            return $crc . "\r\n" . $headers . $this->body;
        }

        return strlen($this->body) . "\r\n" . $this->body;
    }
}
