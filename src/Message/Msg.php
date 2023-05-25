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
    public ?int $timestampNanos = null;

    public static function create(string $data): self
    {
        $args = explode(' ', $data, 5);
        $values = [];
        foreach ($args as $k => $v) {
            if ($v === '') {
                unset($args[$k]);
            }
        }
        $args = array_values($args);

        switch (count($args)) {
            case 3:
                $values = array_combine(['subject', 'sid', 'length'], $args);
                break;

            case 4:
                $values = array_combine(['subject', 'sid', 'replyTo', 'length'], $args);
                if (is_numeric($values['replyTo'])) {
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

        $values = self::tryParseMessageTime($values);

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
            $headerString = substr($payload, 0, $this->hlength);
            foreach (explode("\r\n", $headerString) as $row) {
                if (!$row) {
                    continue;
                }
                if (strpos($row, 'NATS/') !== false) {
                    $parts = explode(' ', $row, 3);
                    if (count($parts) == 1) {
                        // empty header
                        continue;
                    }
                    [$nats, $code, $message] = $parts;
                    $headers['Status-Code'] = trim($code);
                    $headers['Status-Message'] = trim($message);
                } elseif (strpos($row, ':') !== false) {
                    [$key, $value] = explode(':', $row, 2);
                    $headers[trim($key)] = trim($value);
                } else {
                    throw new Exception("Invalid header row: " . $row);
                }
            }
            $payload = substr($payload, $this->hlength);
        }
        $this->payload = new Payload(
            $payload,
            $headers,
            $this->subject,
            $this->timestampNanos
        );

        return $this;
    }

    public function render(): string
    {
        return 'MSG ' . json_encode($this);
    }

    private static function tryParseMessageTime(array $values): array
    {
        if (!array_key_exists('replyTo', $values)
            || !str_starts_with($values['replyTo'], '$JS.ACK')
        ) {
            # This is not a JetStream message
            return $values;
        }

        # old format
        # "$JS.ACK.<stream>.<consumer>.<redeliveryCount><streamSeq><deliverySequence>.<timestamp>.<pending>"
        # new format
        # $JS.ACK.<domain>.<accounthash>.<stream>.<consumer>.<redeliveryCount>.<streamSeq>.<deliverySequence>.<timestamp>.<pending>.<random>
        $tokens = explode('.', $values['replyTo']);
        if (count($tokens) === 9) {
            # if it is an old format we will add two missing items to process tokens in the same way
            array_splice($tokens, 2, 0, ['', '']);
        }

        if (count($tokens) < 11) {
            # Looks like invalid format was given
            return $values;
        }

        $values['timestampNanos'] = (int)$tokens[9];

        return $values;
    }
}
