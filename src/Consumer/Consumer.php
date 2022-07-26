<?php

declare(strict_types=1);

namespace Basis\Nats\Consumer;

use Closure;
use Basis\Nats\Client;

class Consumer
{
    private ?bool $exists = null;
    private bool $interrupt = false;
    private float $delay = 1;
    private float $expires = 0.1;
    private int $batch = 1;
    private int $iterations = PHP_INT_MAX;

    public function __construct(
        public readonly Client $client,
        private readonly Configuration $configuration,
    ) {
    }

    public function create($ifNotExists = true): self
    {
        if (!$this->exists()) {
            $command = 'CONSUMER.DURABLE.CREATE.' . $this->getStream() . '.' . $this->getName();
            $this->client->api($command, $this->configuration->toArray());
            $this->exists = true;
        }

        return $this;
    }

    public function delete(): self
    {
        $this->client->api('CONSUMER.DELETE.' . $this->getStream() . '.' . $this->getName());
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

    public function getBatching(): int
    {
        return $this->batch;
    }

    public function getDelay(): float
    {
        return $this->delay;
    }

    public function getExpires(): float
    {
        return $this->expires;
    }

    public function getIterations(): int
    {
        return $this->iterations;
    }

    public function handle(Closure $handler, Closure $emptyHandler = null): int
    {
        $requestSubject = '$JS.API.CONSUMER.MSG.NEXT.' . $this->getStream() . '.' . $this->getName();
        $args = [
            'batch' => $this->getBatching(),
        ];

        // convert to nanoseconds
        $expires = intval(1_000_000_000 * $this->getExpires());
        if ($expires) {
            $args['expires'] = $expires;
        } else {
            $args['no_wait'] = true;
        }

        $handlerSubject = 'handler.' . bin2hex(random_bytes(4));

        $runtime = new Runtime();

        $this->create();

        $this->client->subscribe($handlerSubject, function ($message) use ($handler, $runtime) {
            if (!$message->isEmpty()) {
                $runtime->empty = false;
                $runtime->processed++;
                $handler($message);
            }
        });

        $iteration = $this->getIterations();
        while ($iteration--) {
            $this->client->publish($requestSubject, $args, $handlerSubject);

            foreach (range(1, $this->batch) as $_) {
                $runtime->empty = true;
                // expires request means that we should receive answer from stream
                $this->client->process($this->expires ? PHP_INT_MAX : null);

                if ($runtime->empty) {
                    if ($emptyHandler) {
                        $emptyHandler();
                    }
                    break;
                }
            }

            if ($this->interrupt) {
                $this->interrupt = false;
                break;
            }

            if ($iteration && $runtime->empty) {
                usleep((int) floor($this->getDelay() * 1_000_000));
            }
        }

        $this->client->unsubscribe($handlerSubject);

        return $runtime->processed;
    }

    public function info()
    {
        return $this->client->api("CONSUMER.INFO." . $this->getStream() . '.' . $this->getName());
    }

    public function interrupt()
    {
        $this->interrupt = true;
    }

    public function setBatching(int $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function setDelay(float $delay): self
    {
        $this->delay = $delay;

        return $this;
    }

    public function setExpires(float $expires): self
    {
        $this->expires = $expires;

        return $this;
    }

    public function setIterations(int $iterations): self
    {
        $this->iterations = $iterations;

        return $this;
    }
}
