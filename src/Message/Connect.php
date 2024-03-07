<?php

declare(strict_types=1);

namespace Basis\Nats\Message;

class Connect extends Prototype
{
    public $headers;
    public $pedantic;
    public $verbose;
    public $auth_token;
    public $echo;
    public $jwt;
    public $lang;
    public $name;
    public $pass;
    public $protocol;
    public $sig;
    public $tls_required;
    public $user;
    public $version;
    public $nkey;

    public function render(): string
    {
        return 'CONNECT ' . json_encode($this);
    }
}
