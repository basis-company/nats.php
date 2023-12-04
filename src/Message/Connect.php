<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Connect extends Prototype
{
    public bool $headers;
    public bool $pedantic;
    public bool $verbose;
    public string $auth_token;
    public string $echo;
    public string $jwt;
    public string $lang;
    public string $name;
    public string $pass;
    public string $protocol;
    public string $sig;
    public string $tls_required;
    public string $user;
    public string $version;
    public string $nkey;

    public function render(): string
    {
        return 'CONNECT ' . json_encode($this);
    }
}
