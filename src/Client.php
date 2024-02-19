<?php

declare(strict_types=1);

namespace Basis\Nats;

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

    private readonly ?Authenticator $authenticator;

    private $socket;
    private $context;
    private array $handlers = [];
    private float $ping = 0;
    private float $pong = 0;
    private ?float $lastDataReadFailureAt = null;
    private string $name = '';
    private array $subscriptions = [];

    private bool $skipInvalidMessages = false;

    public function __construct(
        public readonly Configuration $configuration = new Configuration(),
        public ?LoggerInterface $logger = null,
    ) {
        $this->api = new Api($this);

        $this->authenticator = Authenticator::create($this->configuration);
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

    /**
     * @return $this
     * @throws Throwable
     */
    public function connect(): self
    {
        if ($this->socket) {
            return $this;
        }

        $config = $this->configuration;

        $dsn = "$config->host:$config->port";
        $flags = STREAM_CLIENT_CONNECT;
        $this->context = stream_context_create();
        $this->socket = @stream_socket_client($dsn, $errorCode, $errorMessage, $config->timeout, $flags, $this->context);

        if ($errorCode || !$this->socket) {
            throw new Exception($errorMessage ?: "Connection error", $errorCode);
        }

        $this->setTimeout($config->timeout);

        // Process server info
        $this->process($config->timeout);

        $this->connect = new Connect($config->getOptions());

        if ($this->name) {
            $this->connect->name = $this->name;
        }
        if (isset($this->info->nonce) && $this->authenticator) {
            $this->connect->sig = $this->authenticator->sign($this->info->nonce);
            $this->connect->nkey = $this->authenticator->getPublicKey();
        }

        $this->send($this->connect);

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
        $replyTo = $this->configuration->inboxPrefix . '.' . bin2hex(random_bytes(16));

        $this->subscribe($replyTo, function ($response) use ($replyTo, $handler) {
            $this->unsubscribe($replyTo);
            $handler($response);
        });

        $this->publish($name, $payload, $replyTo);
        $this->process($this->configuration->timeout);

        return $this;
    }

    public function subscribe(string $name, Closure $handler): self
    {
        return $this->doSubscribe($name, null, $handler);
    }

    public function subscribeQueue(string $name, string $group, Closure $handler)
    {
        return $this->doSubscribe($name, $group, $handler);
    }

    public function unsubscribe(string $name): self
    {
        foreach ($this->subscriptions as $i => $subscription) {
            if ($subscription['name'] == $name) {
                unset($this->subscriptions[$i]);
                $this->send(new Unsubscribe(['sid' => $subscription['sid']]));
                unset($this->handlers[$subscription['sid']]);
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

    /**
     * @throws Throwable
     */
    public function process(null|int|float $timeout = 0, bool $reply = true, bool $checkTimeout = true)
    {
        $this->lastDataReadFailureAt = null;
        $max = microtime(true) + $timeout;
        $ping = time() + $this->configuration->pingInterval;

        $iteration = 0;
        while (true) {
            try {
                $line = $this->readLine(1024, "\r\n", $checkTimeout);

                if ($line && ($this->ping || trim($line) != 'PONG')) {
                    break;
                }
                if ($line === false && $ping < time()) {
                    try {
                        $this->send(new Ping([]));
                        $line = $this->readLine(1024, "\r\n");
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
                $this->send(new Pong([]));
                $now = microtime(true);
                if ($now >= $max) {
                    return null;
                }
                return $this->process($max - $now);

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
                $this->handleInfoMessage($message);
                return $this->info = $message;

            case Msg::class:
                $payload = '';
                if (!($message instanceof Msg)) {
                    break;
                }
                if ($message->length) {
                    $iteration = 0;
                    while (strlen($payload) < $message->length) {
                        $payloadLine = $this->readLine($message->length, '', false);
                        if (!$payloadLine) {
                            if ($iteration > 16) {
                                $exception = new LogicException("No payload for message $message->sid");
                                $this->processSocketException($exception);
                                break;
                            }
                            $this->configuration->delay($iteration++);
                            continue;
                        }
                        if (strlen($payloadLine) != $message->length) {
                            $this->logger?->debug(
                                'got ' . strlen($payloadLine) . '/' . $message->length . ': ' . $payloadLine
                            );
                        }
                        $payload .= $payloadLine;
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
                $result = $this->handlers[$message->sid]($message->payload, $message->replyTo);
                if ($reply && $message->replyTo) {
                    $this->publish($message->replyTo, $result);
                }
                return $result;
        }
    }

    /**
     * @throws Exception
     */
    private function handleInfoMessage(Info $info): void
    {
        if (isset($info->tls_verify) && $info->tls_verify) {
            $this->enableTls(true);
        } elseif (isset($info->tls_required) && $info->tls_required) {
            $this->enableTls(false);
        }
    }


    /**
     *
     *
     * @throws Exception
     */
    private function enableTls(bool $requireClientCert): void
    {
        if ($requireClientCert) {
            if (!empty($this->configuration->tlsKeyFile)) {
                if (!file_exists($this->configuration->tlsKeyFile)) {
                    throw new Exception("tlsKeyFile file does not exist: " . $this->configuration->tlsKeyFile);
                }
                stream_context_set_option($this->context, 'ssl', 'local_pk', $this->configuration->tlsKeyFile);
            }
            if (!empty($this->configuration->tlsCertFile)) {
                if (!file_exists($this->configuration->tlsCertFile)) {
                    throw new Exception("tlsCertFile file does not exist: " . $this->configuration->tlsCertFile);
                }
                stream_context_set_option($this->context, 'ssl', 'local_cert', $this->configuration->tlsCertFile);
            }
        }

        if (!empty($this->configuration->tlsCaFile)) {
            if (!file_exists($this->configuration->tlsCaFile)) {
                throw new Exception("tlsCaFile file does not exist: " . $this->configuration->tlsCaFile);
            }
            stream_context_set_option($this->context, 'ssl', 'cafile', $this->configuration->tlsCaFile);
        }

        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            throw new Exception('Failed to connect: Error enabling TLS');
        }
    }


    private function doSubscribe(string $subject, ?string $group, Closure $handler): self
    {
        $sid = bin2hex(random_bytes(4));

        $this->handlers[$sid] = $handler;

        $this->send(new Subscribe([
            'sid' => $sid,
            'subject' => $subject,
            'group' => $group,
        ]));

        $this->subscriptions[] = [
            'name' => $subject,
            'sid' => $sid,
        ];

        return $this;
    }

    private function processSocketException(Throwable $e): self
    {
        if (!$this->configuration->reconnect) {
            $this->logger?->error($e->getMessage());
            throw $e;
        }

        $iteration = 0;

        while (true) {
            try {
                $this->socket = null;
                $this->connect();
            } catch (Throwable $e) {
                $this->configuration->delay($iteration++);
                continue;
            }
            break;
        }

        foreach ($this->subscriptions as $i => $subscription) {
            $this->send(new Subscribe([
                'sid' => $subscription['sid'],
                'subject' => $subscription['name'],
            ]));
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
                $line = $message->render() . "\r\n";
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

    private function readLine(int $length, string $ending = '', bool $checkTimeout = true): string|bool
    {
        $line = stream_get_line($this->socket, $length, $ending);
        if ($line || !$checkTimeout) {
            $this->lastDataReadFailureAt = null;
            return $line;
        }

        $now = microtime(true);
        $this->lastDataReadFailureAt = $this->lastDataReadFailureAt ?? $now;
        $timeWithoutDataRead = $now - $this->lastDataReadFailureAt;

        if ($timeWithoutDataRead > $this->configuration->timeout) {
            throw new LogicException('Socket read timeout');
        }

        return false;
    }
}
