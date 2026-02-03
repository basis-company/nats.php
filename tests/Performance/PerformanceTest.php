<?php

declare(strict_types=1);

namespace Tests\Performance;

use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Tests\FunctionalTestCase;

class PerformanceTest extends FunctionalTestCase
{
    private const CONSUMER_BATCH_SIZE = 50;
    private int $limit = 500_000;
    private int $counter = 0;
    private int $bigMessageIterationLimit = 1000;
    private int $payloadSize = 1024 * 450; // 900kb

    public function testPerformance()
    {
        $client = $this->createClient()->setTimeout(0.1)->setDelay(0);
        $client->connection->setLogger(null);

        $this->logger?->info('start performance test');

        $client->subscribe('hello', function ($n) {
            $this->counter++;
        });

        $publishing = microtime(true);
        foreach (range(1, $this->limit) as $n) {
            $client->publish('hello', 'data-' . $n);
        }
        $publishing = microtime(true) - $publishing;

        $this->logger?->info('publishing', [
            'rps' => floor($this->limit / $publishing),
            'length' => $this->limit,
            'time' => $publishing,
        ]);

        $processing = microtime(true);
        while ($this->counter < $this->limit) {
            $client->process(0);
        }
        $processing = microtime(true) - $processing;

        $this->logger?->info('processing', [
            'rps' => floor($this->limit / $processing),
            'length' => $this->limit,
            'time' => $processing,
        ]);

        // at least 5000rps should be enough for test
        $this->assertGreaterThan(5000, $this->limit / $processing);
    }


    public function testPerformanceWithBigMessages()
    {
        $client = $this->createClient()->setTimeout(0.1)->setDelay(0);
        $client->connection->setLogger(null);

        $bigPayload = bin2hex(random_bytes($this->payloadSize));
        $this->logger?->info('start big message performance test with size: '. strlen($bigPayload) / 1024 .'kb');

        $publishing = microtime(true);
        for ($i = 0; $i < $this->bigMessageIterationLimit; $i++) {
            $client->publish('hello', $bigPayload);
        }
        $publishing = microtime(true) - $publishing;

        $this->logger?->info('publishing', [
            'rps' => floor($this->bigMessageIterationLimit / $publishing),
            'length' => $this->bigMessageIterationLimit,
            'time' => $publishing,
        ]);

        // at least 50rps should be enough for test
        $this->assertGreaterThan(self::CONSUMER_BATCH_SIZE, $this->bigMessageIterationLimit / $publishing);
    }

    public function testFetchNoWait()
    {
        $client = $this->createClient(['timeout' => 10])->setDelay(0);
        $stream = $client->getApi()->getStream('test_fetch_no_wait');
        $stream
            ->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::INTEREST)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setSubjects(['test']);

        $stream->create();

        $consumer = $stream->getConsumer('fetch_no_waiter');
        $consumer->getConfiguration()
            ->setSubjectFilter('test')
            ->setDeliverPolicy(DeliverPolicy::NEW);
        $consumer->create();
        $consumer
            ->setBatching(self::CONSUMER_BATCH_SIZE)
            ->setExpires(0);



        foreach (range(1, 10) as $n) {
            $stream->put('test', 'Hello, NATS JetStream '.date(DATE_RFC3339_EXTENDED).'!');
        }

        $fetching = microtime(true);
        // fetch more than available messages to test no-wait behavior
        $messages = $consumer->getQueue()->fetchAll($consumer->getBatching());
        $fetching = microtime(true) - $fetching;

        $messages = array_filter($messages, static fn ($message) => !$message->payload->isEmpty());

        $this->logger?->info('fetched with no-wait', [
            'length' => count($messages),
            'time' => $fetching,
        ]);

        $this->assertCount(10, $messages);
        $this->assertLessThan(1, $fetching, 'Fetching with no-wait should be fast enough');
    }
}
