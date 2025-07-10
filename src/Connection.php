<?php

declare(strict_types=1);

namespace Basis\Nats;

use Basis\Nats\Message\Connect;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Ok;
use Basis\Nats\Message\Ping;
use Basis\Nats\Message\Publish;
use Basis\Nats\Message\Pong;
use Basis\Nats\Message\Prototype as Message;
use Basis\Nats\Message\Subscribe;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;
use Exception;

class Connection
{
    private $socket;
    private $context;

    private float $activityAt = 0;
    private float $pingAt = 0;
    private float $pongAt = 0;
    private float $prolongateTill = 0;
    private int $packetSize = 1024;

    private ?Authenticator $authenticator;
    private Configuration $config;
    private Connect $connectMessage;
    private Info $infoMessage;

    public function __construct(
        private Client $client,
        public ?LoggerInterface $logger = null,
    ) {
        $this->authenticator = Authenticator::create($client->configuration);
        $this->config = $client->configuration;
    }

    public function getConnectMessage(): Connect
    {
        return $this->connectMessage;
    }

    public function getInfoMessage(): Info
    {
        return $this->infoMessage;
    }

    public function getMessage(null|int|float $timeout = 0): ?Message
    {
        $now = microtime(true);
        $max = $now + $timeout;
        $iteration = 0;

        while (true) {
            $message = null;
            $line = stream_get_line($this->socket, 1024, "\r\n");
            $now = microtime(true);
            if ($line) {
                $message = Factory::create($line);
                $this->activityAt = $now;
                if ($message instanceof Msg) {
                    $payload = $this->getPayload($message->length);
                    $message->parse($payload);
                    $message->setClient($this->client);
                    $this->logger?->debug('receive ' . $line . $payload);
                    return $message;
                }
                $this->logger?->debug('receive ' . $line);
                if ($message instanceof Ok) {
                    continue;
                } elseif ($message instanceof Ping) {
                    $this->sendMessage(new Pong([]));
                } elseif ($message instanceof Pong) {
                    $this->pongAt = $now;
                } elseif ($message instanceof Info) {
                    if (isset($message->tls_verify) && $message->tls_verify && !$this->config->tlsHandshakeFirst) {
                        $this->enableTls(true);
                    } elseif (isset($message->tls_required) && $message->tls_required && !$this->config->tlsHandshakeFirst) {
                        $this->enableTls(false);
                    }
                    return $message;
                }
            } elseif ($this->activityAt && $this->activityAt + $this->config->timeout < $now) {
                if ($this->pingAt && $this->pingAt + $this->config->pingInterval < $now) {
                    if ($this->prolongateTill && $this->prolongateTill < $now) {
                        $this->sendMessage(new Ping());
                    }
                }
            }
            if ($now > $max) {
                break;
            }
            if ($message && $now < $max) {
                $this->logger?->debug('sleep', compact('max', 'now'));
                $this->config->delay($iteration++);
            }
        }

        if ($this->activityAt && $this->activityAt + $this->config->timeout < $now) {
            if ($this->pongAt && $this->pongAt + $this->config->pingInterval < $now) {
                if ($this->prolongateTill && $this->prolongateTill < $now) {
                    $this->processException(new LogicException('Socket read timeout'));
                }
            }
        }

        return null;
    }

    public function ping(): bool
    {
        $this->sendMessage(new Ping());
        $this->getMessage($this->config->timeout);

        return $this->pingAt <= $this->pongAt;
    }

    public function sendMessage(Message $message)
    {
        $this->init();

        $line = $message->render() . "\r\n";
        $length = strlen($line);
        $total = 0;

        $this->logger?->debug('send ' . $line);

        while ($total < $length) {
            try {
                $written = @fwrite($this->socket, substr($line, $total, $this->packetSize));
                if ($written === false) {
                    throw new LogicException('Error sending data');
                }
                if ($written === 0) {
                    throw new LogicException('Broken pipe or closed connection');
                }
                $total += $written;

                if ($length == $total) {
                    break;
                }
            } catch (Throwable $e) {
                $this->processException($e);
                $line = $message->render() . "\r\n";
            }
        }

        unset($line);

        if ($message instanceof Publish) {
            if (strpos($message->subject, '$JS.API.CONSUMER.MSG.NEXT.') === 0) {
                $prolongate = $message->payload->expires / 1_000_000_000;
                $this->prolongateTill = microtime(true) + $prolongate;
            }
        }
        if ($message instanceof Ping) {
            $this->pingAt = microtime(true);
        }
    }

    public function setLogger(?LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function setTimeout(float $value)
    {
        $this->init();
        $seconds = (int) floor($value);
        $milliseconds = (int) (1000 * ($value - $seconds));

        stream_set_timeout($this->socket, $seconds, $milliseconds);
    }

    protected function init()
    {
        if ($this->socket) {
            return $this;
        }

        $config = $this->config;
        $dsn = "$config->host:$config->port";
        $flags = STREAM_CLIENT_CONNECT;
        $this->context = stream_context_create();
        $this->socket = @stream_socket_client($dsn, $error, $errorMessage, $config->timeout, $flags, $this->context);

        if ($error || !$this->socket) {
            throw new Exception($errorMessage ?: "Connection error", $error);
        }

        $this->setTimeout($config->timeout);

        if ($config->tlsHandshakeFirst) {
            $this->enableTls(true);
        }

        $this->connectMessage = new Connect($config->getOptions());

        if ($this->client->getName()) {
            $this->connectMessage->name = $this->client->getName();
        }

        $this->infoMessage = $this->getMessage($config->timeout);
        assert($this->infoMessage instanceof Info);

        if (isset($this->infoMessage->nonce) && $this->authenticator) {
            $this->connectMessage->sig = $this->authenticator->sign($this->infoMessage->nonce);
            $this->connectMessage->nkey = $this->authenticator->getPublicKey();
        }

        $this->sendMessage($this->connectMessage);
    }

    protected function enableTls(bool $requireClientCert): void
    {
        if ($requireClientCert) {
            if (!empty($this->config->tlsKeyFile)) {
                if (!file_exists($this->config->tlsKeyFile)) {
                    throw new Exception("tlsKeyFile file does not exist: " . $this->config->tlsKeyFile);
                }
                stream_context_set_option($this->context, 'ssl', 'local_pk', $this->config->tlsKeyFile);
            }
            if (!empty($this->config->tlsCertFile)) {
                if (!file_exists($this->config->tlsCertFile)) {
                    throw new Exception("tlsCertFile file does not exist: " . $this->config->tlsCertFile);
                }
                stream_context_set_option($this->context, 'ssl', 'local_cert', $this->config->tlsCertFile);
            }
        }

        if (!empty($this->config->tlsCaFile)) {
            if (!file_exists($this->config->tlsCaFile)) {
                throw new Exception("tlsCaFile file does not exist: " . $this->config->tlsCaFile);
            }
            stream_context_set_option($this->context, 'ssl', 'cafile', $this->config->tlsCaFile);
        }

        if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            throw new Exception('Failed to connect: Error enabling TLS');
        }
    }

    protected function getPayload(int $length): string
    {
        $payload = '';
        $iteration = 0;
        while (strlen($payload) < $length) {
            $payloadLine = stream_get_line($this->socket, $length, '');
            if (!$payloadLine) {
                if ($iteration > 16) {
                    break;
                }
                $this->config->delay($iteration++);
                continue;
            }
            if (strlen($payloadLine) != $length) {
                $this->logger?->debug(
                    'got ' . strlen($payloadLine) . '/' . $length . ': ' . $payloadLine
                );
            }
            $payload .= $payloadLine;
        }
        return $payload;
    }

    private function processException(Throwable $e)
    {
        $this->logger?->error($e->getMessage(), ['exception' => $e]);

        if (!$this->config->reconnect) {
            throw $e;
        }

        $iteration = 0;

        while (true) {
            try {
                $this->socket = null;
                $this->init();
            } catch (Throwable $e) {
                $this->config->delay($iteration++);
                continue;
            }
            break;
        }

        foreach ($this->client->getSubscriptions() as $subscription) {
            $this->sendMessage(new Subscribe([
                'sid' => $subscription['sid'],
                'subject' => $subscription['name'],
            ]));
        }

        if ($this->client->requestsSubscribed()) {
            $this->client->subscribeRequests(true);
        }
    }

    public function setPacketSize(int $size): void
    {
        $this->packetSize = $size;
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
