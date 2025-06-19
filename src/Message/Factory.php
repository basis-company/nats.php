<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use LogicException;
use RuntimeException;

abstract class Factory
{
    public static function create(string $line): Prototype
    {
        $message = match ($line) {
            '+OK' => new Ok(),
            'PING' => new Ping(),
            'PONG' => new Pong(),
            default => null,
        };

        if ($message == null) {
            if (!str_contains($line, ' ')) {
                throw new LogicException("Parse message failure: $line");
            }

            [$type, $body] = explode(' ', $line, 2);

            if ($type == '-ERR') {
                $message = trim($body, "'");
                throw new LogicException($message);
            }

            $message = match ($type) {
                'CONNECT' => Connect::create($body),
                'INFO' => Info::create($body),
                'PUBLISH' => Publish::create($body),
                'SUBSCRIBE' => Subscribe::create($body),
                'UNSUBSCRIBE' => Unsubscribe::create($body),
                'HMSG' => Msg::create($body),
                'MSG' => Msg::create($body),
                default => throw new RuntimeException('Unexpected message type: ' . $type),
            };
        }

        return $message;
    }
}
