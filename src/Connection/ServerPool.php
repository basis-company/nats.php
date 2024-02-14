<?php

namespace Basis\Nats\Connection;

use Basis\Nats\Configuration;
use Basis\Nats\Message\Info;
use Exception;
use ArrayIterator;

class ServerPool
{
    private ArrayIterator $serversIterator;
    private readonly bool $ignoreClusterUpdates;
    private readonly bool $serversRandomize;
    private readonly float $reconnectTimeWait;
    private readonly int  $maxReconnectsAllowed;

    /**
     * @throws Exception
     */
    public function __construct(Configuration $conf)
    {
        $this->serversRandomize = $conf->serversRandomize;
        $this->ignoreClusterUpdates = $conf->ignoreClusterUpdates;
        $this->maxReconnectsAllowed = $conf->maxReconnectAttempts;
        $this->reconnectTimeWait = $conf->reconnectTimeWait;

        $serverConfigs = $conf->servers;

        if ($this->serversRandomize) {
            shuffle($serverConfigs);
        }

        $this->serversIterator = new ArrayIterator(array_map(
            function ($e) {
                return new Server($e);
            },
            $serverConfigs
        ));
    }

    public function allExceededReconnectionAttempts(): bool
    {
        if ($this->hasServers()) {
            $arr = $this->serversIterator->getArrayCopy();
            foreach ($arr as $e) {
                if ($e->getReconnectAttempts() < $this->maxReconnectsAllowed && !$e->isLameDuck()) {
                    return false;
                }
            }
        }
        return true;
    }

    public function totalReconnectionAttempts(): int
    {
        if ($this->hasServers()) {
            $arr = $this->serversIterator->getArrayCopy();
            return array_reduce($arr, function ($carry, $e) {
                return $carry + $e->getReconnectAttempts();
            });
        }
        return 0;
    }

    public function hasServers(): bool
    {
        return $this->serversIterator->count() > 0;
    }

    /**
     * @return Server
     * @throws Exception
     */
    public function nextServer(): Server
    {
        $counter = 0;
        $max = ($this->maxReconnectsAllowed +1) * $this->serversIterator->count();

        while ($counter++ < $max) {
            $current = $this->serversIterator->current();
            $current->incrementReconnectAttempts();

            $this->serversIterator->next();

            if (!$this->serversIterator->valid()) {
                $this->serversIterator->rewind();
            }

            if ($current->getReconnectAttempts() <= $this->maxReconnectsAllowed && !$current->isLameDuck()) {
                if ($current->getReconnectAttempts() > 0 && $this->reconnectTimeWait > 0) {
                    usleep((int) floor(intval($this->reconnectTimeWait * 1_000)));
                }
                return $current;
            }
        }

        throw new Exception("Connection error: maximum reconnect attempts exceeded for all servers.");
    }


    /**
     * @throws Exception
     */
    public function processInfoMessage(Info $message): void
    {
        $server = $this->serversIterator->current();
        if ($server) {
            $server->resetReconnectAttempts();
        }

        if (!$this->ignoreClusterUpdates) {
            if (isset($message->ldm) && $message->ldm) {
                $server = $this->serversIterator->current();
                $server->setLameDuck(true);
            };

            if (isset($message->connect_urls) && !empty($message->connect_urls)) {
                $clusterUrls = $message->connect_urls;
                $arrCopy = $this->serversIterator->getArrayCopy();

                $arrConns = array_map(function ($e) {
                    return $e->getConnectionString();
                }, $arrCopy);

                $toBeRemoved = array_diff($arrConns, $clusterUrls);
                $toBeAdded   = array_diff($clusterUrls, $arrConns);

                $newArr = array_filter($arrCopy, function ($e) use ($toBeRemoved) {
                    return !in_array($e->getConnectionString(), $toBeRemoved);
                });

                foreach ($toBeAdded as $e) {
                    $newArr[] = new Server($e, true);
                }

                if ($this->serversRandomize) {
                    shuffle($newArr);
                }

                //reset lame duck mode when included on the server list
                array_walk($newArr, function ($e) use ($clusterUrls) {
                    $e->resetReconnectAttempts();
                    $conn = $e->getConnectionString();
                    if (in_array($conn, $clusterUrls)) {
                        $e->setLameDuck(false);
                    }
                });

                $this->serversIterator = new ArrayIterator($newArr);
            }
        }
    }
}
