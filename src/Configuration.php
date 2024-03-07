<?php

declare(strict_types=1);

namespace Basis\Nats;

use InvalidArgumentException;

class Configuration
{
    public $pedantic;
    public $reconnect;
    public $verbose;
    public $port;
    public $host;
    public $lang;
    public $version;
    public $timeout;
    public $pingInterval;

    public $inboxPrefix;

    public $jwt;
    public $pass;
    public $token;
    public $user;
    public $nkey;

    public $tlsKeyFile;
    public $tlsCertFile;
    public $tlsCaFile;

    public const DELAY_CONSTANT = 'constant';
    public const DELAY_LINEAR = 'linear';
    public const DELAY_EXPONENTIAL = 'exponential';

    protected $delay = 0.001;
    protected $delayMode = self::DELAY_CONSTANT;

    protected $defaults = [
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
        $milliseconds = intval($this->delay * 1000);

        switch ($this->delayMode) {
            case self::DELAY_EXPONENTIAL:
                $milliseconds = $milliseconds ** $iteration;
                break;

            case self::DELAY_LINEAR:
                $milliseconds = $milliseconds * $iteration;
                break;
        }

        usleep($milliseconds * 1000);
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
