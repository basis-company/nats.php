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
    public readonly int $pingInterval;

    public readonly string $inboxPrefix;

    public readonly ?string $jwt;
    public readonly ?string $pass;
    public readonly ?string $token;
    public readonly ?string $user;
    public readonly ?string $nkey;

    public readonly ?string $tlsKeyFile;
    public readonly ?string $tlsCertFile;
    public readonly ?string $tlsCaFile;

    public readonly array $servers;
    public readonly bool  $serversRandomize;
    public readonly bool  $ignoreClusterUpdates;
    public readonly int   $maxReconnectAttempts;
    public readonly float $reconnectTimeWait;

    public const DELAY_CONSTANT = 'constant';
    public const DELAY_LINEAR = 'linear';
    public const DELAY_EXPONENTIAL = 'exponential';

    protected float $delay = 0.001;
    protected string $delayMode = self::DELAY_CONSTANT;

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
        'nkey' => null,
        'verbose' => false,
        'version' => 'dev',
        'pingInterval' => 2,
        'inboxPrefix' => '_INBOX',
        'tlsKeyFile' => null,
        'tlsCertFile' => null,
        'tlsCaFile' => null,
        'servers' => [],
        'serversRandomize' => true,
        'ignoreClusterUpdates' => false,
        'maxReconnectAttempts' => 10,
        'reconnectTimeWait' => 0.200
    ];

    /**
     * @param array<string, string|int|bool|null> ...$options
     */
    public function __construct(array ...$options)
    {
        $config = array_merge($this->defaults, ...$options);
        foreach ($config as $k => $v) {
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
            'protocol' => 1,
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

    public function delay(int $iteration)
    {
        $milliseconds = intval($this->delay * 1_000);

        switch ($this->delayMode) {
            case self::DELAY_EXPONENTIAL:
                $milliseconds = $milliseconds ** $iteration;
                break;

            case self::DELAY_LINEAR:
                $milliseconds = $milliseconds * $iteration;
                break;
        }

        usleep($milliseconds * 1_000);
    }

    public function setDelay(float $delay, string $mode = self::DELAY_CONSTANT): self
    {
        if (!in_array($mode, [self::DELAY_CONSTANT, self::DELAY_EXPONENTIAL, self::DELAY_LINEAR])) {
            throw new InvalidArgumentException("Invalid mode: $mode");
        }

        $this->delay = $delay;
        $this->delayMode = $mode;

        return $this;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function getDelayMode(): string
    {
        return $this->delayMode;
    }
}
