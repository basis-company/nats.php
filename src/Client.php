<?php

declare(strict_types=1);

namespace Basis\Nats;

use BadMethodCallException;
use Basis\Nats\Message\Connect;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
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

    private array $handlers = [];
    private float $pong = 0;
    private $socket;

    public function __construct(
        public readonly Configuration $configuration = new Configuration(),
        private ?LoggerInterface $logger = null,
    ) {
        $this->api = new Api($this);
    }

    public function api($command, array $args = [], ?Closure $callback = null): ?object
    {
        $subject = strtoupper("\$js.api.$command");
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
            $this->send($this->connect);

            $this->processMessage();
        }
    }

    public function decode(string $value)
    {
        return json_decode($value) ?: $value;
    }

    public function dispatch(string $name, mixed $payload, float $timeout = 60)
    {
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
            $this->processMessage();
        }

        if (!$context->processed) {
            throw new LogicException("Processing timeout");
        }

        return $context->result;
    }

    public function encode($value): string
    {
        return is_object($value) || is_array($value) ? json_encode($value) : (string) $value;
    }

    public function getApi(): Api
    {
        return $this->api;
    }

    public function ping(): bool
    {
        $start = microtime(true);
        $this->send('PING');
        $this->processMessage();
        return $start < $this->pong;
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): self
    {
        return $this->send(new Publish([
            'payload' => $this->encode($payload),
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
        $this->processMessage();

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

    public function processMessage()
    {
        $line = stream_get_line($this->socket, 1024, "\r\n");
        if (!$line) {
            return;
        }

        switch (trim($line)) {
            case 'PING':
                $this->logger?->debug('ping');
                $this->send('PONG');
                return $this->processMessage();

            case 'PONG':
                $this->logger?->debug('pong');
                $this->pong = microtime(true);
                return;

            case '+OK':
                $this->logger?->debug('ok');
                return $this->processMessage();
        }

        try {
            $message = Factory::create(trim($line));
        } catch (Throwable $exception) {
            $this->logger?->debug($line);
            throw $exception;
        }

        switch (get_class($message)) {
            case Info::class:
                $this->logger?->debug('receive ' . $message);
                return $this->info = $message;

            case Msg::class:
                if (!$message->length) {
                    // read empty line
                    $message->payload .= stream_get_line($this->socket, 0, "\r\n");
                } else {
                    // read message payload
                    while (strlen($message->payload) < $message->length) {
                        $message->payload .= stream_get_line($this->socket, 1024, "\r\n");
                    }
                }
                if (!array_key_exists($message->sid, $this->handlers)) {
                    throw new LogicException("No handler for message $message->sid");
                }
                $this->logger?->debug('receive message', (array) $message);
                $result = $this->handlers[$message->sid]($this->decode($message->payload));
                if ($message->replyTo) {
                    $this->send(new Publish([
                        'subject' => $message->replyTo,
                        'payload' => $this->encode($result),
                    ]));
                }
                break;
        }
    }

    private function send($message): self
    {
        $line = (string) $message . "\r\n";
        $length = strlen($line);

        $this->logger?->debug('send', compact('line'));

        $this->connect();

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
            $msg = substr($msg, -$length);
        }

        return $this;
    }
}
