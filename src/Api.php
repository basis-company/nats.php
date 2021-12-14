<?php

declare(strict_types=1);

namespace Basis\Nats;

use Basis\Nats\Stream\Stream;

class Api
{
    private array $streams = [];

    public function __construct(public readonly Client $client)
    {
    }

    public function getInfo()
    {
        return $this->client->api('info');
    }

    public function getStreamList(): array
    {
        return $this->client->api('stream.list')->streams ?: [];
    }

    public function getStreamNames(): array
    {
        return $this->client->api('stream.names')->streams ?: [];
    }

    public function getStream(string $name): Stream
    {
        if (!array_key_exists($name, $this->streams)) {
            $this->streams[$name] = new Stream($this->client, $name);
        }

        return $this->streams[$name];
    }
}
