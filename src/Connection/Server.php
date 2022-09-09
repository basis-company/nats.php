<?php

namespace Basis\Nats\Connection;

use Exception;

class Server
{
    private readonly string $host;
    public readonly int $port;
    public int $reconnectAttempts;
    public bool $lameDuck;
    public bool $gossiped;

    /**
     * @throws Exception
     */
    public function __construct(string $hostport, bool $gossiped = false)
    {
        if (empty($hostport) || !str_contains($hostport, ':') || 2 !== sizeof(explode(":", $hostport))) {
            throw new Exception("Invalid server configuration: " .$hostport);
        }

        $parts = explode(":", $hostport);

        if (empty($parts[0]) || !is_numeric($parts[1])) {
            throw new Exception("Invalid server configuration: " .$hostport);
        }

        $this->host = $parts[0];
        $this->port = intval($parts[1]);
        $this->gossiped = $gossiped;
        $this->lameDuck = false;
        $this->reconnectAttempts = -1;
    }

    /**
     * @return string
     */
    public function getConnectionString(): string
    {
        return $this->host . ":" . $this->port;
    }

    /**
     */
    public function incrementReconnectAttempts(): void
    {
        $this->reconnectAttempts = $this->reconnectAttempts + 1;
    }

    /**
     * @return int
     */
    public function getReconnectAttempts(): int
    {
        return $this->reconnectAttempts;
    }

    /**
     * @return int
     */
    public function resetReconnectAttempts(): int
    {
        return $this->reconnectAttempts = 0;
    }

    /**
     */
    public function setLameDuck(bool $lameDuck): void
    {
        $this->lameDuck = $lameDuck;
    }

    /**
     * @return bool
     */
    public function isLameDuck(): bool
    {
        return $this->lameDuck;
    }
}
