<?php

declare(strict_types=1);

namespace Basis\Nats\KeyValue;

use Basis\Nats\Client;
use Basis\Nats\Consumer\Configuration as ConsumerConfiguration;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\Stream;
use Exception;

class Bucket
{
    private ?bool $exists = null;
    private ?Stream $stream = null;
    private Configuration $configuration;

    public function __construct(
        public readonly Client $client,
        public readonly string $name,
    ) {
        $this->configuration = new Configuration($name);
    }

    public function get(string $key)
    {
        try {
            $entry = $this->getEntry($key);
            return $entry ? $entry->value : null;
        } catch (Exception $e) {
            if ($e->getMessage() == 'no message found') {
                return null;
            }
            throw $e;
        }
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getEntry(string $key): ?Entry
    {
        $response = $this->getStream()
            ->getLastMessage($this->getSubject($key));

        if (!$response->message || !property_exists($response->message, 'data')) {
            return null;
        }

        $revision = $response->message->seq;
        $value = base64_decode($response->message->data);

        return new Entry($this->name, $key, $value, $revision, $response->message->time);

    }

    /**
     * @return Entry[]
     */
    public function getAll(): array
    {
        $entries = [];

        $stream = $this->getStream();
        if (!$stream->exists()) {
            return $entries;
        }

        $stream_name = $stream->getName();
        $configuration = new ConsumerConfiguration($stream_name);
        $consumer = $stream->createEphemeralConsumer($configuration);
        $subject_prefix_length = 1 + strlen(sprintf('$KV.%s', $this->name));

        $consumer->handle(function (Payload $payload) use (&$entries, $subject_prefix_length) {
            if ($payload->subject === null) {
                return;
            }

            $key = substr($payload->subject, $subject_prefix_length);
            $entries[] = new Entry('', $key, $payload->body, 0);
        }, function () use ($consumer) {
            $consumer->interrupt();
        });

        $consumer->delete();

        return $entries;
    }

    public function getStatus(): Status
    {
        return new Status($this->name, $this->getStream()->info());
    }

    public function getStream(): Stream
    {
        if (!$this->stream) {
            $this->stream = $this->client->getApi()
                ->getStream("KV_$this->name");

            if (!$this->stream->exists()) {
                $this->getConfiguration()
                    ->configureStream($this->stream->getConfiguration());

                $this->stream->create();
            }
        }
        return $this->stream;
    }

    public function getSubject(string $key): string
    {
        return "\$KV.$this->name.$key";
    }

    public function delete($key)
    {
        $payload = new Payload('', [
            'KV-Operation' => 'DEL',
        ]);

        return $this->getStream()
            ->publish($this->getSubject($key), $payload);
    }

    public function purge($key)
    {
        $payload = new Payload('', [
            'KV-Operation' => 'PURGE',
            'Nats-Rollup' => 'sub',
        ]);

        return $this->getStream()
            ->publish($this->getSubject($key), $payload);
    }

    public function put(string $key, string $value): int
    {
        return $this->getStream()
            ->publish($this->getSubject($key), $value)
            ->seq;
    }

    public function update(string $key, string $value, int $revision): int
    {
        $headers = [
            'Nats-Expected-Last-Subject-Sequence' => (string) $revision
        ];

        $subject = $this->getSubject($key);
        $payload = new Payload($value, $headers);

        return $this->getStream()
            ->publish($subject, $payload)
            ->seq;
    }
}
