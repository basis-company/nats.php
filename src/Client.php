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
    public readonly Connect $connect;
    public readonly Info $info;
    public readonly Api $api;

    private $socket;
    private array $handlers = [];
    private float $pong = 0;
    private string $name = '';

    // delay on empty result
    private float $delay = 0.001;

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

    public function connect()
    {
        if (!$this->socket) {
            $config = $this->configuration;

            $dsn = "tcp://$config->host:$config->port";
            $flags = STREAM_CLIENT_CONNECT;
            $this->socket = @stream_socket_client($dsn, $errno, $errstr, $config->timeout, $flags);

            if (!$this->socket) {
                throw new Exception($errstr, $errno);
            }

            $this->setTimeout($config->timeout);

            $this->connect = new Connect($config->getOptions());
            if ($this->name) {
                $this->connect->name = $this->name;
            }
            $this->send($this->connect);
            $this->process(PHP_INT_MAX);
        }
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
        $timestamp = microtime(true);
        $this->send(new Ping([]));

        return $timestamp <= $this->process(PHP_INT_MAX);
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
        $this->process(PHP_INT_MAX);

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

    public function setDelay(float $delay): self
    {
        $this->delay = $delay;
        return $this;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function setTimeout(float $value): self
    {
        $seconds = (int) floor($value);
        $milliseconds = (int) (1000 * ($value - $seconds));

        stream_set_timeout($this->socket, $seconds, $milliseconds);

        return $this;
    }

    public function process(null|int|float $timeout = 0)
    {
        $max = microtime(true) + $timeout;

        while (!($line = stream_get_line($this->socket, 1024, "\r\n"))) {
            if (microtime(true) > $max) {
                return null;
            }
            // 1ms sleep
            usleep(intval($this->delay * 1_000_000));
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
                    while (strlen($payload) < $message->length) {
                        $payload = stream_get_line($this->socket, $message->length);
                        if (strlen($payload) != $message->length) {
                            $this->logger?->debug('got ' . strlen($payload) . '/' . $message->length . ': ' . $payload);
                        }
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

    private function send(Prototype $message): self
    {
        $this->connect();

        $line = $message->render() . "\r\n";
        $length = strlen($line);

        $this->logger?->debug('send ' . $line);

        while (strlen($line)) {
            $written = fwrite($this->socket, $line);
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
        }

        if ($this->configuration->verbose && $line !== "PING\r\n") {
            // get feedback
            $this->process(PHP_INT_MAX);
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
