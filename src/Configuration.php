<?php

declare(strict_types=1);

namespace Basis\Nats;

use InvalidArgumentException;

class Configuration
{
    public const DELAY_CONSTANT = 'constant';
    public const DELAY_LINEAR = 'linear';
    public const DELAY_EXPONENTIAL = 'exponential';

    protected float $delay;
    protected string $delayMode;

    /**
     * @param array<string, string|int|bool|null> $options
     */
    public function __construct(
        array $options = [], // deprecated
        array $options2 = [], // deprecated multi option array support
        array $options3 = [], // deprecated multi option array support
        public string $host = 'localhost',
        public int $port = 4222,
        public ?string $user = null,
        public ?string $jwt = null,
        public ?string $pass = null,
        public ?string $token = null,
        public ?string $nkey = null,
        public ?string $tlsKeyFile = null,
        public ?string $tlsCertFile = null,
        public ?string $tlsCaFile = null,
        public bool $tlsHandshakeFirst = false,
        public bool $pedantic = false,
        public bool $reconnect = true,
        public bool $verbose = false,
        public float $timeout = 1,
        public int $pingInterval = 2,
        float $delay = 0.001,
        string $delayMode = self::DELAY_CONSTANT,
        public string $lang = 'php',
        public string $version = 'dev',
        public string $inboxPrefix = '_INBOX',
    ) {

        $this->setDelay($delay, $delayMode);

        foreach (array_merge($options, $options2, $options3) as $k => $v) {
            if (!property_exists($this, $k)) {
                throw new InvalidArgumentException("Invalid config option $k");
            }
            if ($k == 'delayMode') {
                $this->setDelay($this->delay, $v);
            } elseif ($k == 'delay') {
                $this->setDelay($v, $this->delayMode);
            } else {
                $this->$k = $v;
            }
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
