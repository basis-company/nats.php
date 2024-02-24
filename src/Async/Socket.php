<?php

declare(strict_types=1);

namespace Basis\Nats\Async;

use Amp\CancelledException;
use Amp\DeferredCancellation;
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

    private DeferredCancellation $cancelReader;

    public function __construct(
        private readonly \Amp\Socket\Socket $socket,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly int $idleTimeout = 0,
    ) {
        $this->queue = $queue = new Queue();

        $this->iterator = $queue->iterate();
        $this->parser = new Parser($this->queue);

        EventLoop::queue($this->connectReader(...));

        if ($this->idleTimeout > 0) {
            $this->logger->debug('registering idle timeout');
            $pinger = EventLoop::repeat($this->idleTimeout, $this->ping(...));
            $this->socket->onClose(fn () => EventLoop::cancel($pinger));
        }
    }

    public function switchToAsync(int $concurrency, \Closure $closure)
    {
        if ($this->async) {
            return;
        }
        $this->logger->debug('switching to async');
        $this->async = true;
        $this->queue = new Queue($concurrency);
        $this->parser = new Parser($this->queue);
        EventLoop::queue(function () use ($closure) {
            foreach ($this->queue->iterate() as $message) {
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
        $this->logger->debug('switching to sync');

        $this->queue = $queue = new Queue();

        $this->iterator = $queue->iterate();
        $this->parser = new Parser($this->queue);
    }

    public function enableTls(): void
    {
        $this->cancelReader->cancel();
        $this->logger->debug('enabling tls');
        $this->socket->setupTls();

    }

    public function isClosed(): bool
    {
        return $this->socket->isClosed();
    }

    public function __destruct()
    {
        $this->socket->close();
    }

    private function connectReader(): void
    {
        $this->logger->debug('connecting reader to socket');
        $this->cancelReader = new DeferredCancellation();
        try {
            while (null !== $chunk = $this->socket->read($this->cancelReader->getCancellation())) {
                $this->logger->debug('Received chunk', ['chunk' => $chunk]);
                $this->parser->push($chunk);
            }

            $this->logger->warning('Socket disconnected');

            $this->parser->cancel();
            $this->queue->complete();
        } catch (CancelledException) {
            $this->logger->debug('Reader disconnected for TLS negotiation');
        } catch (\Throwable $exception) {
            $this->socket->close();
            throw $exception;
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

        $this->logger->debug('Received line', ['line' => $line]);

        if (!($result = $this->handleLine($line))) {
            return $this->read($timeout, $reply);
        }

        return $result;
    }

    private function handleLine(\Throwable|string|array $line): Prototype|null
    {
        $this->logger->debug('Handling line', ['line' => $line]);

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
        $this->logger->debug('Sending line', ['line' => $line]);
        // just throw the exception to be caught by the client, which is responsible for connection logic
        $this->socket->write($line);
    }

    public function close(): void
    {
        $this->logger->debug('Closing socket');
        $this->socket->close();
    }

    private function ping(): void
    {
        $this->logger->debug('ping requested');
        if (time() - $this->pingEvent > $this->idleTimeout) {
            $this->logger->debug('ping sent');
            $this->write('PING');
            $this->pingEvent = time();
        }
    }
}
