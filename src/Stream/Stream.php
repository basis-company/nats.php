<?php

declare(strict_types=1);

namespace Basis\Nats\Stream;

use Basis\Nats\Client;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\Configuration as ConsumerConfiguration;

class Stream
{
    private array $consumers = [];
    private ?Configuration $configuration = null;

    public function __construct(public readonly Client $client, private string $name)
    {
    }

    public function create(): self
    {
        $this->client->api("STREAM.CREATE." . $this->getName(), $this->getConfiguration()->toArray());

        return $this;
    }

    public function createIfNotExists(): self
    {
        if (!$this->exists()) {
            return $this->create();
        }
        return $this;
    }

    public function delete(): self
    {
        if ($this->exists()) {
            $this->client->api("STREAM.DELETE." . $this->getName());
        }

        return $this;
    }

    public function purge(): self
    {
        if ($this->exists()) {
            $this->client->api("STREAM.PURGE." . $this->getName());
        }

        return $this;
    }

    public function exists(): bool
    {
        return in_array($this->getName(), $this->client->getApi()->getStreamNames());
    }

    public function getConfiguration(): Configuration
    {
        if ($this->configuration === null) {
            if ($this->exists()) {
                $this->configuration = Configuration::fromObject($this->info()->getValues()->config);
            } else {
                $this->configuration = new Configuration($this->getName());
            }
        }
        return $this->configuration;
    }

    public function createEphemeralConsumer(ConsumerConfiguration $configuration): Consumer
    {
        $configuration->setEphemeral(true);

        $consumer = new Consumer($this->client, $this->getName());
        $consumer->setConfiguration($configuration);
        $consumer->create();

        $this->consumers[$consumer->getName()] = $consumer;
        return $consumer;
    }

    public function getConsumer(string $name): Consumer
    {
        if (!array_key_exists($name, $this->consumers)) {
            $this->consumers[$name] = new Consumer($this->client, $this->getName(), $name);
        }

        return $this->consumers[$name];
    }

    public function getConsumerNames(): array
    {
        return $this->client->api('CONSUMER.NAMES.' . $this->getName())->consumers;
    }

    public function getLastMessage(string $subject)
    {
        return $this->client->api('STREAM.MSG.GET.' . $this->getName(), [
            'last_by_subj' => $subject
        ]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function info()
    {
        return $this->client->api("STREAM.INFO." . $this->getName());
    }

    public function put(string $subject, mixed $payload): self
    {
        $this->client->publish($subject, $payload);
        return $this;
    }

    public function publish(string $subject, mixed $payload)
    {
        return $this->client->dispatch($subject, $payload);
    }

    public function update()
    {
        $this->client->api("STREAM.UPDATE." . $this->getName(), $this->getConfiguration()->toArray());
    }
}
