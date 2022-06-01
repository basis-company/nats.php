<?php

declare(strict_types=1);

namespace Basis\Nats;

use BadMethodCallException;
use Basis\Nats\Message\Connect;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Ping;
use Basis\Nats\Message\Pong;
use Basis\Nats\Message\Prototype;
use Basis\Nats\Message\Publish;
use Basis\Nats\Message\Subscribe;
use Basis\Nats\Message\Unsubscribe;
use Closure;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class Client
{
    public Connect $connect;
    public Info $info;
    public readonly Api $api;

    private $socket;
    private array $handlers = [];
    private float $ping = 0;
    private float $pong = 0;
    private string $name = '';

    private bool $skipInvalidMessages = false;

    public function __construct(
        public readonly Configuration $configuration = new Configuration(),
        public ?LoggerInterface $logger = null,
    ) {
        $this->api = new Api($this);
    }

    public function api($command, array $args = [], ?Closure $callback = null): ?object
    {
        $subject = "\$JS.API.$command";
        $options = json_encode((object) $args);

        if ($callback) {
            return $this->request($subject, $options, $callback);
        }

        $result = $this->dispatch($subject, $options);

        if (property_exists($result, 'error')) {
            throw new Exception($result->error->description, $result->error->err_code);
        }

        if (!$result) {
            return null;
        }

        return $result;
    }

    public function connect(): self
    {
        if (!$this->socket) {
            $config = $this->configuration;

            $dsn = "tcp://$config->host:$config->port";
            $flags = STREAM_CLIENT_CONNECT;
            $this->socket = @stream_socket_client($dsn, $errorCode, $errorMessage, $config->timeout, $flags);

            if ($errorCode || !$this->socket) {
                throw new Exception($errorMessage ?: "Connection error", $errorCode);
            }

            $this->setTimeout($config->timeout);

            $this->connect = new Connect($config->getOptions());
            if ($this->name) {
                $this->connect->name = $this->name;
            }
            $this->send($this->connect);
            $this->process($config->timeout);
        }

        return $this;
    }

    public function dispatch(string $name, mixed $payload, ?float $timeout = null)
    {
        if ($timeout === null) {
            $timeout = $this->configuration->timeout;
        }

        $context = (object) [
            'processed' => false,
            'result' => null,
            'threshold' => microtime(true) + $timeout,
        ];

        $this->request($name, $payload, function ($result) use ($context) {
            $context->processed = true;
            $context->result = $result;
        });

        while (!$context->processed && microtime(true) < $context->threshold) {
            $this->process();
        }

        if (!$context->processed) {
            throw new LogicException("Processing timeout");
        }

        return $context->result;
    }

    public function getApi(): Api
    {
        return $this->api;
    }

    public function ping(): bool
    {
        $this->ping = microtime(true);
        $this->send(new Ping([]));
        $this->process($this->configuration->timeout);
        $result = $this->ping <= $this->pong;
        $this->ping = 0;

        return $result;
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): self
    {
        return $this->send(new Publish([
            'payload' => Payload::parse($payload),
            'replyTo' => $replyTo,
            'subject' => $name,
        ]));
    }

    public function request(string $name, mixed $payload, Closure $handler): self
    {
        $sid = 'inbox.' . bin2hex(random_bytes(4));

        $this->subscribe($sid, function ($response) use ($sid, $handler) {
            $this->unsubscribe($sid);
            $handler($response);
        });

        $this->publish($name, $payload, $sid);
        $this->process($this->configuration->timeout);

        return $this;
    }

    public function subscribe(string $name, Closure $handler): self
    {
        $sid = bin2hex(random_bytes(4));

        $this->handlers[$sid] = $handler;

        $this->send(new Subscribe([
            'sid' => $sid,
            'subject' => $name,
        ]));

        $this->subscriptions[] = [
            'name' => $name,
            'sid' => $sid,
        ];

        return $this;
    }

    public function unsubscribe(string $name): self
    {
        foreach ($this->subscriptions as $i => $subscription) {
            if ($subscription['name'] == $name) {
                unset($this->subscriptions[$i]);
                unset($this->handlers[$subscription['sid']]);
                $this->send(new Unsubscribe(['sid' => $subscription['sid']]));
            }
        }

        return $this;
    }

    public function setDelay(float $delay, string $mode = Configuration::DELAY_CONSTANT): self
    {
        $this->configuration->setDelay($delay, $mode);
        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function setTimeout(float $value): self
    {
        $this->connect();
        $seconds = (int) floor($value);
        $milliseconds = (int) (1000 * ($value - $seconds));

        stream_set_timeout($this->socket, $seconds, $milliseconds);

        return $this;
    }

    public function process(null|int|float $timeout = 0)
    {
        $max = microtime(true) + $timeout;
        $ping = time() + $this->configuration->pingInterval;

        $iteration = 0;
        while (true) {
            try {
                $line = stream_get_line($this->socket, 1024, "\r\n");
                if ($line && ($this->ping || trim($line) != 'PONG')) {
                    break;
                }
                if ($line === false && $ping < time()) {
                    try {
                        $this->send(new Ping([]));
                        $line = stream_get_line($this->socket, 1024, "\r\n");
                        $ping = time() + $this->configuration->pingInterval;
                        if ($line && ($this->ping || trim($line) != 'PONG')) {
                            break;
                        }
                    } catch (Throwable $e) {
                        if ($this->ping) {
                            return;
                        }
                        $this->processSocketException($e);
                    }
                }
                $now = microtime(true);
                if ($now >= $max) {
                    return null;
                }
                $this->logger?->debug('sleep', compact('max', 'now'));
                $this->configuration->delay($iteration++);
            } catch (Throwable $e) {
                $this->processSocketException($e);
            }
        }

        switch (trim($line)) {
            case 'PING':
                $this->logger?->debug('receive ' . $line);
                return $this->send(new Pong([]));

            case 'PONG':
                $this->logger?->debug('receive ' . $line);
                return $this->pong = microtime(true);

            case '+OK':
                return $this->logger?->debug('receive ' . $line);
        }

        try {
            $message = Factory::create(trim($line));
        } catch (Throwable $exception) {
            $this->logger?->debug($line);
            throw $exception;
        }

        switch (get_class($message)) {
            case Info::class:
                $this->logger?->debug('receive ' . $line);
                return $this->info = $message;

            case Msg::class:
                $payload = '';
                if ($message->length) {
                    $iteration = 0;
                    while (strlen($payload) < $message->length) {
                        $line = stream_get_line($this->socket, $message->length);
                        if (!$line) {
                            if ($iteration > 16) {
                                $exception = new LogicException("No payload for message $message->sid");
                                $this->processSocketException($exception);
                                break;
                            }
                            $this->configuration->delay($iteration++);
                            continue;
                        }
                        if (strlen($line) != $message->length) {
                            $this->logger?->debug('got ' . strlen($line) . '/' . $message->length . ': ' . $line);
                        }
                        $payload .= $line;
                    }
                }
                $message->parse($payload);
                $this->logger?->debug('receive ' . $line . $payload);
                if (!array_key_exists($message->sid, $this->handlers)) {
                    if ($this->skipInvalidMessages) {
                        return;
                    }
                    throw new LogicException("No handler for message $message->sid");
                }
                $result = $this->handlers[$message->sid]($message->payload);
                if ($message->replyTo) {
                    $this->send(new Publish([
                        'subject' => $message->replyTo,
                        'payload' => Payload::parse($result),
                    ]));
                }
                break;
        }
    }

    private function processSocketException(Throwable $e): self
    {
        if (!$this->configuration->reconnect) {
            $this->logger?->error($e->getMessage());
            throw $e;
        }

        $this->socket = null;
        $iteration = 0;

        while (true) {
            try {
                $this->connect();
            } catch (Throwable $e) {
                $this->configuration->delay($iteration++);
                continue;
            }
            break;
        }

        foreach ($this->subscriptions as $i => $subscription) {
            $fn = $this->handlers[$subscription['sid']];
            unset($this->subscriptions[$i]);
            unset($this->handlers[$subscription['sid']]);
            $this->send(new Unsubscribe(['sid' => $subscription['sid']]));
            $this->subscribe($subscription['name'], $fn);
        }
        return $this;
    }

    private function send(Prototype $message): self
    {
        $this->connect();

        $line = $message->render() . "\r\n";
        $length = strlen($line);

        $this->logger?->debug('send ' . $line);

        while (strlen($line)) {
            try {
                $written = @fwrite($this->socket, $line, 1024);
                if ($written === false) {
                    throw new LogicException('Error sending data');
                }
                if ($written === 0) {
                    throw new LogicException('Broken pipe or closed connection');
                }
                if ($length == $written) {
                    break;
                }
                $line = substr($line, $written);
            } catch (Throwable $e) {
                $this->processSocketException($e);
            }
        }

        if ($this->configuration->verbose && $line !== "PING\r\n") {
            // get feedback
            $this->process($this->configuration->timeout);
        }

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function skipInvalidMessages(bool $skipInvalidMessages): self
    {
        $this->skipInvalidMessages = $skipInvalidMessages;
        return $this;
    }
}
