<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use Basis\Nats\Client;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\Configuration as ConsumerConfiguration;
use DomainException;

class Stream
{
    private array $consumers = [];
    private readonly Configuration $configuration;

    public function __construct(public readonly Client $client, string $name)
    {
        $this->configuration = new Configuration($name);
    }

    public function api()
    {
        return $this->client->api(...func_get_args());
    }

    public function create()
    {
        $this->api("stream.create." . $this->getName(), $this->configuration->toArray());

        return $this;
    }

    public function delete(): self
    {
        if ($this->exists()) {
            $this->api("stream.delete." . $this->getName());
        }

        return $this;
    }

    public function exists(): bool
    {
        return in_array($this->getName(), $this->client->getApi()->getStreamNames());
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getConsumer(string $name): Consumer
    {
        if (!array_key_exists($name, $this->consumers)) {
            $configuration = new ConsumerConfiguration($this->getName(), $name);
            $this->consumers[$name] = new Consumer($this->client, $configuration);
        }

        return $this->consumers[$name];
    }

    public function getConsumerNames(): array
    {
        return $this->api('consumer.names.' . $this->getName())->consumers;
    }

    public function getName(): string
    {
        return $this->configuration->getName();
    }

    public function info()
    {
        return $this->api("stream.info." . $this->getName());
    }

    public function put(string $subject, array $data): self
    {
        $this->configuration->validateSubject($subject);
        $this->client->publish($subject, $data);
        return $this;
    }

    public function update()
    {
        $this->api("stream.update." . $this->getName(), $this->configuration->toArray());
    }
}
