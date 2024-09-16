<?php

declare(strict_types=1);

namespace Basis\Nats;

use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Publish;
use Basis\Nats\Message\Subscribe;
use Basis\Nats\Message\Unsubscribe;
use Basis\Nats\Service\Service;
use Closure;
use Exception;
use LogicException;
use Psr\Log\LoggerInterface;

class Client
{
    public readonly Api $api;

    private string $name = '';

    /** @var array<Closure|Queue> */
    private array $handlers = [];
    private array $subscriptions = [];

    private array $services = [];

    private bool $skipInvalidMessages = false;

    public function __construct(
        public readonly Configuration $configuration = new Configuration(),
        public ?LoggerInterface $logger = null,
        public ?Connection $connection = null,
    ) {
        $this->api = new Api($this);
        if (!$connection) {
            $this->connection = new Connection(client: $this, logger: $logger);
        }
    }

    public function api($command, array $args = [], ?Closure $callback = null): ?object
    {
        $subject = "\$JS.API.$command";
        $options = json_encode((object) $args);

        if ($callback) {
            return $this->request($subject, $options, $callback);
        }

        $result = $this->dispatch($subject, $options);

        if ($result->error ?? false) {
            throw new Exception($result->error->description, $result->error->err_code);
        }

        if (!$result) {
            return null;
        }

        return $result;
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
        return $this->connection->ping();
    }

    public function publish(string $name, mixed $payload, ?string $replyTo = null): self
    {
        $this->connection->sendMessage(new Publish([
            'payload' => Payload::parse($payload),
            'replyTo' => $replyTo,
            'subject' => $name,
        ]));

        return $this;
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

    public function subscribe(string $name, ?Closure $handler = null): self|Queue
    {
        return $this->doSubscribe($name, null, $handler);
    }

    public function subscribeQueue(string $name, string $group, ?Closure $handler = null): self|Queue
    {
        return $this->doSubscribe($name, $group, $handler);
    }

    public function unsubscribe(string|Queue $name): self
    {
        if ($name instanceof Queue) {
            $name = $name->subject;
        }
        foreach ($this->subscriptions as $i => $subscription) {
            if ($subscription['name'] == $name) {
                unset($this->subscriptions[$i]);
                $this->connection->sendMessage(new Unsubscribe(['sid' => $subscription['sid']]));
                unset($this->handlers[$subscription['sid']]);
            }
        }

        return $this;
    }

    public function getSubscriptions(): array
    {
        return $this->subscriptions;
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

    public function process(null|int|float $timeout = 0, bool $reply = true): mixed
    {
        $message = $this->connection->getMessage($timeout);

        if ($message instanceof Msg) {
            if (!array_key_exists($message->sid, $this->handlers)) {
                if ($this->skipInvalidMessages) {
                    return null;
                }
                throw new LogicException("No handler for message $message->sid");
            }
            $handler = $this->handlers[$message->sid];
            if ($handler instanceof Queue) {
                $handler->handle($message);
                return $handler;
            } else {
                $result = $handler($message->payload, $message->replyTo);
                if ($reply && $message->replyTo) {
                    $message->reply($result);
                }
                return $result;
            }
        } else {
            return $message;
        }
    }

    private function doSubscribe(string $subject, ?string $group, ?Closure $handler = null): self|Queue
    {
        $sid = bin2hex(random_bytes(4));
        if ($handler == null) {
            $this->handlers[$sid] = new Queue($this, $subject);
        } else {
            $this->handlers[$sid] = $handler;
        }

        $this->connection->sendMessage(new Subscribe([
            'sid' => $sid,
            'subject' => $subject,
            'group' => $group,
        ]));

        $this->subscriptions[] = [
            'name' => $subject,
            'sid' => $sid,
        ];

        if ($handler == null) {
            return $this->handlers[$sid];
        }
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setTimeout(float $value): self
    {
        $this->connection->setTimeout($value);
        return $this;
    }

    public function skipInvalidMessages(bool $skipInvalidMessages): self
    {
        $this->skipInvalidMessages = $skipInvalidMessages;
        return $this;
    }

    public function unsubscribeAll(): self
    {
        foreach ($this->subscriptions as $index => $subscription) {
            unset($this->subscriptions[$index]);
            $this->connection->sendMessage(new Unsubscribe(['sid' => $subscription['sid']]));
            unset($this->handlers[$subscription['sid']]);
        }

        return $this;
    }

    public function disconnect(): self
    {
        $this->unsubscribeAll();
        $this->connection->close();

        return $this;
    }

    public function service(string $name, string $description, string $version): Service
    {
        if (!array_key_exists($name, $this->services)) {
            $this->services[$name] = new Service($this, $name, $description, $version);
        }

        return $this->services[$name];
    }
}
