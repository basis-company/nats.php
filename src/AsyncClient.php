<?php
declare(strict_types=1);

namespace Basis\Nats;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ConnectContext;
use Amp\TimeoutCancellation;
use Basis\Nats\Async\Socket;
use Basis\Nats\Message\Connect;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Ping;
use Basis\Nats\Message\Prototype;
use Phan\Language\Context;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use Revolt\EventLoop;

use function Amp\Socket\socketConnector;

class AsyncClient extends Client
{
    public readonly Api $api;

    private Socket $socket;

    public function __construct(
        Configuration $configuration = new Configuration(),
        LoggerInterface|null $logger = new NullLogger(),
    ) {
        parent::__construct($configuration, $logger);
    }

    public function connect(): Client
    {
        if(isset($this->socket)) {
            return $this;
        }

        $config = $this->configuration;
        $dsn = "$config->host:$config->port";

        try {
            $context = (new ConnectContext())->withConnectTimeout($config->timeout);
            $tlsContext = null;
            if($config->tlsKeyFile || $config->tlsCertFile) {
                $tlsContext ??= new ClientTlsContext();
                $tlsContext = $tlsContext->withCertificate(new Certificate($config->tlsCertFile, $config->tlsKeyFile));
            }
            if($config->tlsCaFile) {
                $tlsContext ??= new ClientTlsContext();
                $tlsContext = $tlsContext->withCaFile($config->tlsCaFile);
            }
            if($tlsContext) {
                $context = $context->withTlsContext($tlsContext);
            }
            $this->socket = new Socket(socketConnector()->connect($dsn, $context));
        } catch(\Throwable $exception) {
            // todo: handle exception
            throw $exception;
        }

        $info = $this->process($config->timeout);
        assert($info instanceof Info);

        $connect = new Connect($config->getOptions());
        if($this->name) {
            $connect->name = $this->name;
        }
        if(isset($info->nonce) && $this->authenticator) {
            $connect->sig = $this->authenticator->sign($info->nonce);
            $connect->nkey = $this->authenticator->getPublicKey();
        }

        $this->send($connect);

        return $this;
    }

    public function setTimeout(float $value): Client
    {
        throw new \LogicException('timeout is set via configuration');
    }

    public function ping():bool {
        $this->send(new Ping([]));

        // todo: handle this result better
        return true;
    }

    public function background(bool $enableAutoReply, int $concurrency = 10): \Closure  {
        $this->socket->switchToAsync($concurrency, fn(Prototype|null $message) => $message && $this->onMessage($message, $enableAutoReply, false));
        return $this->socket->switchToSync(...);
    }

    private function onMessage(Prototype $message, bool $reply = true, bool $async = false): Info|null {
        switch($message::class) {
            case Info::class:
                if(($message->tls_verify ?? false) || ($message->tls_required ?? false )) {
                    $this->socket->enableTls();
                }
                return $message;
            case Msg::class:
                assert($message instanceof Msg);
                $handler = $this->handlers[$message->sid] ?? null;
                if(!$handler) {
                    if($this->skipInvalidMessages) {
                        return null;
                    }
                    throw new \LogicException('No handler for ' . $message->sid);
                }

                if($async) {
                    EventLoop::queue(function () use ($handler, $message, $reply) {
                        $result = $handler($message->payload, $message->replyTo);
                        if ($reply && $message->replyTo) {
                            $this->publish($message->replyTo, $result);
                        }
                    });
                } else {
                    $result = $handler($message->payload, $message->replyTo);
                    if($reply && $message->replyTo) {
                        $this->publish($message->replyTo, $result);
                    }
                }
                break;
        }

        return null;
    }

    public function process(null|int|float $timeout = 0, bool $reply = true, bool $async = false): Info|null {
        if($this->socket->isAsync()) {
            return null;
        }
        $this->lastDataReadFailureAt = null;
        $message = $this->socket->read($timeout, $reply);
        if($message === null) {
            return null;
        }

        return $this->onMessage($message, $reply);
    }

    protected function send(Prototype $message): self {
        $this->connect();

        $line = $message->render() . "\r\n";

        $this->socket->write($line);

        return $this;
    }
}
