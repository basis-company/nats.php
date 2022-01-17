<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Closure;
use Basis\Nats\Client;

class Consumer
{
    private ?bool $exists = null;
    private int $batch = 1;

    public function __construct(
        public readonly Client $client,
        private readonly Configuration $configuration,
    ) {
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

    public function handle(Closure $handler, int $limit = PHP_INT_MAX, float $delay = 1): int
    {
        $method = 'consumer.msg.next.' . $this->getStream() . '.' . $this->getName();
        $requestSubject = strtoupper("\$js.api.$method");

        $args = [
            'batch' => $this->batch,
            'no_wait' => true,
        ];

        $handlerSubject = 'handler.' . bin2hex(random_bytes(4));

        $runtime = (object) [
            'processed' => 0,
            'empty' => false,
        ];

        $this->client->subscribe($handlerSubject, function ($message) use ($handler, $runtime) {
            if ($message->isEmpty()) {
                $runtime->empty = true;
            } else {
                $runtime->processed++;
                $handler($message);
            }
        });

        while ($limit--) {
            $this->client->publish($requestSubject, $args, $handlerSubject);
            $this->client->process(true);

            $runtime->empty = false;

            foreach (range(1, $this->batch) as $_) {
                $this->client->process();

                if ($runtime->empty) {
                    break;
                }
            }

            if ($limit && $runtime->empty) {
                usleep((int) floor($delay * 1_000_000));
            }
        }

        $this->client->unsubscribe($handlerSubject);

        return $runtime->processed;
    }

    public function setBatching(int $batch): self
    {
        $this->batch = $batch;

        return $this;
    }
}
