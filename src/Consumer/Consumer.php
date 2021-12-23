<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Closure;
use Basis\Nats\Client;

class Consumer
{
    private ?bool $exists = null;

    public function __construct(
        public readonly Client $client,
        private readonly Configuration $configuration,
    ) {
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getName(): string
    {
        return $this->getConfiguration()->getName();
    }

    public function getStream(): string
    {
        return $this->getConfiguration()->getStream();
    }

    public function create(): self
    {
        $command = 'consumer.durable.create.' . $this->getStream() . '.' . $this->getName();
        $this->client->api($command, $this->configuration->toArray());
        $this->exists = true;

        return $this;
    }

    public function delete(): self
    {
        $this->client->api('consumer.delete.' . $this->getStream() . '.' . $this->getName());
        $this->exists = false;

        return $this;
    }

    public function exists(): bool
    {
        if ($this->exists !== null) {
            return $this->exists;
        }
        $consumers = $this->client->getApi()->getStream($this->getStream())->getConsumerNames();
        return $this->exists = in_array($this->getName(), $consumers);
    }

    public function handle(Closure $handler, int $limit = PHP_INT_MAX, float $delay = 1)
    {
        $method = 'consumer.msg.next.' . $this->getStream() . '.' . $this->getName();

        $args = [
            'batch' => 1,
            'no_wait' => true,
        ];

        while ($limit--) {
            $this->client->api($method, $args, function ($message) use ($handler, $delay) {
                if ($message) {
                    $handler($message);
                } else {
                    usleep((int) floor($delay * 1_000_000));
                }
            });
        }
    }
}
