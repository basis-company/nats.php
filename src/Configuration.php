<?php

declare(strict_types=1);

namespace Basis\Nats;

use InvalidArgumentException;

class Configuration
{
    public readonly bool $pedantic;
    public readonly bool $reconnect;
    public readonly bool $verbose;
    public readonly int $port;
    public readonly string $host;
    public readonly string $lang;
    public readonly string $version;
    public readonly float $timeout;

    public readonly ?string $jwt;
    public readonly ?string $pass;
    public readonly ?string $token;
    public readonly ?string $user;

    protected array $defaults = [
        'host' => 'localhost',
        'jwt' => null,
        'lang' => 'php',
        'pass' => null,
        'pedantic' => false,
        'port' => 4222,
        'reconnect' => true,
        'timeout' => 1,
        'token' => null,
        'user' => null,
        'verbose' => false,
        'version' => 'dev',
    ];

    public function __construct(array $config = [])
    {
        foreach (array_merge($this->defaults, $config) as $k => $v) {
            if (!property_exists($this, $k)) {
                throw new InvalidArgumentException("Invalid config option $k");
            }
            $this->$k = $v;
        }
    }

    public function getOptions(): array
    {
        $options = [
            'lang' => $this->lang,
            'pedantic' => $this->pedantic,
            'verbose' => $this->verbose,
            'version' => $this->version,
            'headers' => true,
        ];

        if ($this->user !== null) {
            $options['user'] = $this->user;
            $options['pass'] = $this->pass;
        } elseif ($this->token !== null) {
            $options['auth_token'] = $this->token;
        } elseif ($this->jwt !== null) {
            $options['jwt'] = $this->jwt;
        }

        return $options;
    }
}
