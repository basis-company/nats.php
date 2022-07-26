<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

use LogicException;

final class Factory
{
    private function __construct()
    {
    }

    public static function create(string $line): Prototype
    {
        if (!str_contains($line, ' ')) {
            throw new LogicException("Parse message failure: $line");
        }

        [$type, $body] = explode(' ', $line, 2);

        $nick = ucfirst(strtolower($type));

        if ($nick == '-err') {
            $message = trim($body, "'");
            throw new LogicException($message);
        }

        if ($nick == 'Hmsg') {
            $nick = 'Msg';
        }

        $class = 'Basis\\Nats\\Message\\' . $nick;

        return call_user_func_array([$class, 'create'], [$body]);
    }
}
