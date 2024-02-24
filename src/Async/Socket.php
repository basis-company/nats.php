<?php

declare(strict_types=1);

namespace Basis\Nats\Async;

use Amp\CancelledException;
use Amp\Pipeline\ConcurrentIterator;
use Amp\Pipeline\Queue;
use Amp\TimeoutCancellation;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Prototype;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Revolt\EventLoop;

class Socket
{
    private ConcurrentIterator $iterator;
    private Queue $queue;
    private Parser $parser;

    private int $pingEvent = 0;

    private bool $async = false;

    public function __construct(
        private readonly \Amp\Socket\Socket $socket,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $idleTimeout = 0,
    ) {
        $this->queue = $queue = new Queue();

        $this->iterator = $queue->iterate();
        $this->parser = new Parser($this->queue);

        EventLoop::queue(function () use ($socket) {
            try {
                while (null !== $chunk = $socket->read()) {
                    $this->parser->push($chunk);
                }

                $this->parser->cancel();
                $this->queue->complete();
            } catch (\Throwable $exception) {
                // todo: handle exception
                throw $exception;
            } finally {
                $socket->close();
            }
        });

        if ($this->idleTimeout > 0) {
            EventLoop::repeat($this->idleTimeout, $this->ping(...));
        }
    }

    public function read(int|float|null $timeout = null, bool $reply = true): Prototype|null
    {
        $cancellation = null;
        if ($timeout) {
            $cancellation = new TimeoutCancellation($timeout, 'Operation timed out');
        }

        try {
            if (!$this->iterator->continue($cancellation)) {
                return null;
            }
        } catch (CancelledException $exception) {
            $this->logger->debug("timed out waiting for message: ", ['exception' => $exception]);
            return null;
        }

        $line = $this->iterator->getValue();

        if (!($result = $this->handleLine($line))) {
            return $this->read($timeout, $reply);
        }

        return $result;
    }

    private function handleLine(\Throwable|string|array $line): Prototype|null
    {
        if ($line instanceof \Throwable) {
            throw $line;
        }

        $payload = '';
        if (is_array($line)) {
            [$line, $payload] = $line;
        }

        // handle ping/pongs here, notify the owner of this socket, then wait for the next message
        switch (trim($line)) {
            case 'PING':
                $this->logger->debug("Receive: $line");
                $this->write("PONG");
                ($this->onPing ?? static fn () => null)();
                return null;
            case 'PONG':
                $this->logger->debug("Receive: $line");
                ($this->onPong ?? static fn () => null)();
                return null;
            case '+OK':
                $this->logger->debug("Message acknowledged");
                return null;
        }

        try {
            $result = Factory::create($line);
            if ($result instanceof Msg) {
                return $result->parse($payload);
            }
            return $result;
        } catch (\Throwable $exception) {
            $this->logger->debug($line);
            throw $exception;
        }
    }

    public function write(string $line): void
    {
        // just throw the exception to be caught by the client, which is responsible for connection logic
        $this->socket->write($line);
    }

    public function close(): void
    {
        $this->socket->close();
    }

    public function switchToAsync(int $concurrency, \Closure $closure)
    {
        if ($this->async) {
            return;
        }
        $this->async = true;
        $this->queue = new Queue($concurrency);
        $this->parser = new Parser($this->queue);
        EventLoop::queue(function () use ($closure) {
            foreach($this->queue->iterate() as $message) {
                $closure($this->handleLine($message));
            }
            //$this->queue->pipe()
            //->sequential()
            //->concurrent($concurrency)
            //    ->forEach(fn ($message) => $closure($this->handleLine($message)));
        });
    }

    public function isAsync(): bool
    {
        return $this->async;
    }

    public function switchToSync()
    {
        $this->queue = $queue = new Queue();

        $this->iterator = $queue->iterate();
        $this->parser = new Parser($this->queue);
    }

    public function enableTls(): void
    {
        $this->socket->setupTls();
    }

    public function readArbitraryLine(int $assertLength): string|null
    {
        if (!$this->iterator->continue()) {
            return null;
        }

        $line = $this->iterator->getValue();
        if ($line instanceof \Throwable) {
            throw $line;
        }

        if (($actualLenght = mb_strlen($line, '8bit')) !== $assertLength) {
            throw new \RuntimeException('Expected payload of ' . $assertLength . ' bytes, but got ' . $actualLenght);
        }

        return $line;
    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function __destruct()
    {
        $this->socket->close();
    }

    private function ping(): void
    {
        if(time() - $this->pingEvent > $this->idleTimeout) {
            $this->write('PING');
            $this->pingEvent = time();
        }
    }
}
