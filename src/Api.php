<?php

declare(strict_types=1);

namespace Basis\Nats;

use Basis\Nats\KeyValue\Bucket;
use Basis\Nats\Stream\Stream;

class Api
{
    private array $streams = [];
    private array $buckets = [];

    public function __construct(public readonly Client $client)
    {
    }

    public function getBucket(string $name): Bucket
    {
        if (!array_key_exists($name, $this->buckets)) {
            $this->buckets[$name] = new Bucket($this->client, $name);
        }

        return $this->buckets[$name];
    }

    public function getInfo()
    {
        return $this->client->api('INFO');
    }

    public function getStreamList(): array
    {
        return $this->client->api('STREAM.LIST')->streams ?: [];
    }

    public function getStreamNames(): array
    {
        return $this->client->api('STREAM.NAMES')->streams ?: [];
    }

    public function getStream(string $name): Stream
    {
        if (!array_key_exists($name, $this->streams)) {
            $this->streams[$name] = new Stream($this->client, $name);
        }

        return $this->streams[$name];
    }
}
