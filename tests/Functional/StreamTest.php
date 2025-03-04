<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\Configuration;
use Basis\Nats\Consumer\Consumer;
use Basis\Nats\Consumer\ReplayPolicy;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\ConsumerLimits;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Tests\FunctionalTestCase;

class StreamTest extends FunctionalTestCase
{
    private mixed $called;

    private bool $empty;

    public function testInvalidAckPolicy()
    {
        $this->expectExceptionMessage("Invalid ack policy: fast");

        $this->createClient()
            ->getApi()
            ->getStream('acking')
            ->getConsumer('tester')
            ->getConfiguration()
            ->setAckPolicy('fast');
    }

    public function testInvalidDeliverPolicy()
    {
        $this->expectExceptionMessage("Invalid deliver policy: turtle");

        $this->createClient()
            ->getApi()
            ->getStream('acking')
            ->getConsumer('tester')
            ->getConfiguration()
            ->setDeliverPolicy('turtle');
    }

    public function testInvalidReplayPolicy()
    {
        $this->expectExceptionMessage("Invalid replay policy: fast");

        $this->createClient()
            ->getApi()
            ->getStream('acking')
            ->getConsumer('tester')
            ->getConfiguration()
            ->setReplayPolicy('fast');
    }

    public function testNack()
    {
        $client = $this->createClient();
        $stream = $client->getApi()->getStream('nacks');
        $stream->getConfiguration()->setSubjects(['nacks'])->setRetentionPolicy(RetentionPolicy::INTEREST);
        $stream->create();

        $consumer = $stream->getConsumer('nacks');
        $consumer->setExpires(5);
        $consumer->getConfiguration()
            ->setSubjectFilter('nacks')
            ->setReplayPolicy(ReplayPolicy::INSTANT)
            ->setAckPolicy(AckPolicy::EXPLICIT);

        $consumer->create();

        $stream->publish('nacks', 'first');
        $stream->publish('nacks', 'second');

        $this->assertSame(2, $consumer->info()->num_pending);

        $queue = $consumer->getQueue();
        $message = $queue->fetch();
        $this->assertNotNull($message);
        $this->assertSame((string) $message->payload, 'first');
        $message->nack(0.5);

        $this->assertSame(1, $consumer->info()->num_ack_pending);
        $this->assertSame(1, $consumer->info()->num_pending);

        $queue->setTimeout(0.1);
        $messages = $queue->fetchAll();
        $this->assertCount(1, $messages);
        [$message] = $messages;
        $this->assertSame((string) $message->payload, 'second');
        $message->progress();
        $message->ack();

        usleep(100_000);
        $messages = $queue->fetchAll();
        $this->assertCount(0, $messages);
        $this->assertSame(1, $consumer->info()->num_ack_pending);
        $this->assertSame(0, $consumer->info()->num_pending);

        usleep(500_000);
        $messages = $queue->fetchAll();
        $this->assertCount(1, $messages);
        [$message] = $messages;
        $message->ack();

        usleep(100_000);
        $this->assertSame(0, $consumer->info()->num_ack_pending);
        $this->assertSame(0, $consumer->info()->num_pending);
    }

    public function testPurge()
    {
        $client = $this->createClient();
        $stream = $client->getApi()->getStream('purge');
        $stream->getConfiguration()->setSubjects(['purge'])->setRetentionPolicy(RetentionPolicy::WORK_QUEUE);
        $stream->create();

        $consumer = $stream->getConsumer('purge');
        $consumer->setExpires(5);
        $consumer->getConfiguration()
            ->setSubjectFilter('purge')
            ->setReplayPolicy(ReplayPolicy::INSTANT)
            ->setAckPolicy(AckPolicy::EXPLICIT);

        $consumer->create();

        $stream->publish('purge', 'first');
        $stream->publish('purge', 'second');

        $this->assertSame(2, $consumer->info()->num_pending);

        $stream->purge();

        $this->assertSame(0, $consumer->info()->num_pending);
    }

    public function testConsumerExpiration()
    {
        $client = $this->createClient(['timeout' => 0.2, 'delay' => 0.1]);
        $stream = $client->getApi()->getStream('empty');
        $stream->getConfiguration()
            ->setSubjects(['empty']);

        $stream->create();
        $consumer = $stream->getConsumer('empty')->create();
        $consumer->getConfiguration()->setSubjectFilter('empty');

        $info = $client->connection->getInfoMessage();

        $consumer->setIterations(1)->setExpires(3)->handle(function () {
        });
        $this->assertSame($info, $client->connection->getInfoMessage());
    }

    public function testSetConfigRetentionPolicyToMaxAge(): void
    {
        $api = $this->createClient()
            ->getApi();
        $stream = $api->getStream('tester');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::LIMITS)
            ->setMaxAge(3_600_000_000_000)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $config = $stream->info()->config;

        self::assertSame($config->retention, 'limits');
        self::assertSame($config->storage, 'memory');
        self::assertSame($config->subjects, ['tester.greet', 'tester.bye']);
        self::assertSame($config->max_age, 3_600_000_000_000);

        $stream->getConfiguration()
            ->setSubjects(['tester.greet']);
        $stream->update();

        self::assertSame($stream->info()->config->subjects, ['tester.greet']);

        $stream->getConfiguration()
            ->setMaxAge(3_600_000_000_001)
            ->setSubjects(['tester.greet', 'tester.bye']);
        $stream->update();

        $api = $this->createClient()
            ->getApi();

        $api->getStream('tester')
            ->getConfiguration()
            ->fromArray(
                $stream->getConfiguration()
                    ->toArray()
            );

        $configuration = $api->getStream('tester')
            ->getConfiguration();
        self::assertSame($configuration->getRetentionPolicy(), 'limits');
        self::assertSame($configuration->getStorageBackend(), 'memory');
        self::assertSame($configuration->getSubjects(), ['tester.greet', 'tester.bye']);
    }

    public function testDeduplication()
    {
        $stream = $this->getClient()->getApi()->getStream('tester');
        $stream->getConfiguration()
            ->setSubjects(['tester'])
            ->setDuplicateWindow(0.5); // 500ms windows duplicate

        $stream->createIfNotExists();
        $stream->createIfNotExists(); // should not provide an error

        // windows value using nanoseconds
        $this->assertEquals(0.5 * 1_000_000_000, $stream->info()->getValue('config.duplicate_window'));

        $stream->put('tester', new Payload("hello", [
            'Nats-Msg-Id' => 'the-message'
        ]));

        $stream->put('tester', new Payload("hello", [
            'Nats-Msg-Id' => 'the-message'
        ]));

        $consumer = $stream->getConsumer('tester')
            ->setIterations(1)
            ->create();

        $this->called = null;
        $this->assertWrongNumPending($consumer, 1);

        $consumer->handle($this->persistMessage(...));

        $this->assertNotNull($this->called);

        $this->assertWrongNumPending($consumer);

        $stream->put('tester', new Payload("hello", [
            'Nats-Msg-Id' => 'the-message'
        ]));
        $this->assertWrongNumPending($consumer);

        // 500ms sleep
        usleep(500 * 1_000);

        $stream->put('tester', new Payload("hello", [
            'Nats-Msg-Id' => 'the-message'
        ]));
        $this->assertWrongNumPending($consumer, 1);

        usleep(500 * 1_000);

        $stream->put('tester', new Payload("hello", [
            'Nats-Msg-Id' => 'the-message'
        ]));
        $this->assertWrongNumPending($consumer, 2);

        $consumer->handle(function ($msg) {
            $this->assertSame($msg->getHeader('Nats-Msg-Id'), 'the-message');
        });

        $this->assertWrongNumPending($consumer, 1);
    }

    public function testInterrupt()
    {
        $stream = $this->getClient()->getApi()->getStream('no_messages');
        $stream->getConfiguration()->setSubjects(['cucumber']);
        $stream->create();

        $consumer = $stream->getConsumer('test_consumer');
        $consumer->getConfiguration()->setSubjectFilter('cucumber')->setMaxAckPending(2);
        $consumer->setDelay(0)->create();

        $this->assertSame(2, $consumer->info()->getValue('config.max_ack_pending'));

        $this->getClient()->publish('cucumber', 'message1');
        $this->getClient()->publish('cucumber', 'message2');
        $this->getClient()->publish('cucumber', 'message3');
        $this->getClient()->publish('cucumber', 'message4');

        $this->assertWrongNumPending($consumer, 4);

        $consumer->setBatching(1)->setIterations(2)
            ->handle(function ($response) use ($consumer) {
                $consumer->interrupt();
                $this->logger?->info('interrupt!!');
            });

        $this->assertWrongNumPending($consumer, 3);

        $consumer->setBatching(2)->setIterations(1)
            ->handle(function ($response) use ($consumer) {
                $consumer->interrupt();
                $this->logger?->info('interrupt!!');
            });

        $this->assertWrongNumPending($consumer, 1);
    }

    public function testNoMessages()
    {
        $this->called = false;
        $this->empty = false;

        $stream = $this->createClient(['reconnect' => false])->getApi()->getStream('no_messages');
        $stream->getConfiguration()->setSubjects(['cucumber']);
        $stream->create();

        $consumer = $stream->getConsumer('test_consumer');
        $consumer->getConfiguration()->setSubjectFilter('cucumber');

        $consumer->create()
            ->setDelay(0)
            ->setIterations(1)
            ->setExpires(1)
            ->handle(function ($response) {
                $this->called = $response;
            }, function () {
                $this->empty = true;
            });

        $this->assertSame($consumer->getDelay(), floatval(0));
        $this->assertFalse($this->called);
        $this->assertTrue($this->empty);
    }

    public function testSingletons()
    {
        $api = $this->getClient()->getApi();
        $this->assertSame($api, $this->getClient()->getApi());

        $stream = $api->getStream('tester')->createIfNotExists();
        $this->assertSame($stream, $api->getStream('tester'));

        $consumer = $stream->getConsumer('worker');
        $this->assertSame($consumer, $stream->getConsumer('worker'));

        $this->assertCount(1, $api->getStreamList());
    }

    public function testConfguration()
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('tester');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $config = $stream->info()->config;
        $this->assertSame($config->retention, 'workqueue');
        $this->assertSame($config->storage, 'memory');
        $this->assertSame($config->subjects, ['tester.greet', 'tester.bye']);

        $stream->getConfiguration()->setSubjects(['tester.greet']);
        $stream->update();

        $this->assertSame($stream->info()->config->subjects, ['tester.greet']);

        $stream->getConfiguration()->setSubjects(['tester.greet', 'tester.bye']);
        $stream->update();

        $api = $this->createClient()->getApi();

        $api->getStream('tester')
            ->getConfiguration()
            ->fromArray($stream->getConfiguration()->toArray());

        $configuration = $api->getStream('tester')->getConfiguration();
        $this->assertSame($configuration->getRetentionPolicy(), 'workqueue');
        $this->assertSame($configuration->getStorageBackend(), 'memory');
        $this->assertSame($configuration->getSubjects(), ['tester.greet', 'tester.bye']);
    }

    public function testConsumer()
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $this->assertSame($api->getInfo()->streams, 0);

        $stream = $api->getStream('my_stream');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $this->called = null;
        $consumer = $stream->getConsumer('greet_consumer');
        $consumer->getConfiguration()->setSubjectFilter('tester.greet');
        $consumer->create();

        $this->assertNull($this->called);
        $consumer->setIterations(1);
        $consumer->handle($this->persistMessage(...));

        $this->assertNull($this->called);
        $stream->put('tester.greet', [ 'name' => 'nekufa' ]);
        $consumer->setIterations(1)->setExpires(1);
        $consumer->handle($this->persistMessage(...));

        $this->assertNotNull($this->called);
        $this->assertSame($this->called->name, 'nekufa');
        $this->assertNotNull($this->called->timestampNanos);

        $this->called = null;
        $consumer = $stream->getConsumer('bye');
        $consumer->getConfiguration()->setSubjectFilter('tester.bye');
        $consumer->getConfiguration()->setAckPolicy('explicit');
        $consumer->create();

        $stream->put('tester.greet', [ 'name' => 'nekufa' ]);
        $consumer->setIterations(1)->setDelay(0);
        $consumer->handle($this->persistMessage(...));

        $this->assertNull($this->called);
    }

    public function testConsumerInactiveThreshold()
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $this->assertSame($api->getInfo()->streams, 0);

        $stream = $api->getStream('my_stream_with_threshold');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $this->called = null;
        $consumer = $stream->getConsumer('greet_consumer');

        $oneSecondInNanoseconds = 1000000000;
        $consumer->getConfiguration()->setSubjectFilter('tester.greet')->setInactiveThreshold($oneSecondInNanoseconds);
        $consumer->create();

        $this->assertCount(1, $stream->getConsumerNames());

        sleep(2);

        $this->assertCount(0, $stream->getConsumerNames());
    }

    public function testStreamInactiveThreshold()
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $this->assertSame($api->getInfo()->streams, 0);

        $stream = $api->getStream('my_stream_with_threshold');

        $oneSecondInNanoseconds = 1000000000;
        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setSubjects(['tester.greet', 'tester.bye'])
            ->setConsumerLimits(
                [
                    ConsumerLimits::INACTIVE_THRESHOLD => $oneSecondInNanoseconds,
                ]
            );

        $stream->create();

        $this->called = null;
        $consumer = $stream->getConsumer('greet_consumer');

        $consumer->getConfiguration()->setSubjectFilter('tester.greet');
        $consumer->create();

        $this->assertCount(1, $stream->getConsumerNames());

        sleep(2);

        $this->assertCount(0, $stream->getConsumerNames());
    }

    public function testEphemeralConsumer()
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $this->assertSame($api->getInfo()->streams, 0);

        $stream = $api->getStream('my_stream');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $this->called = null;

        $configuration = new Configuration('my_stream');
        $configuration->setSubjectFilter('tester.greet');
        $consumer1 = $stream->createEphemeralConsumer($configuration);
        $this->assertNull($this->called);

        // check that consumer can be received by name after creation
        $this->assertSame($consumer1, $stream->getConsumer($consumer1->getName()));

        $consumer1->setIterations(1);
        $consumer1->handle($this->persistMessage(...));

        $this->assertNull($this->called);
        $stream->put('tester.greet', [ 'name' => 'oxidmod' ]);
        $consumer1->setIterations(1)->setExpires(1);
        $consumer1->handle($this->persistMessage(...));

        $this->assertNotNull($this->called);
        $this->assertSame($this->called->name, 'oxidmod');

        $this->called = null;
        $configuration = new Configuration('my_stream');
        $configuration->setSubjectFilter('tester.bye')->setAckPolicy('explicit');
        $consumer2 = $stream->createEphemeralConsumer($configuration);

        $stream->put('tester.greet', [ 'name' => 'oxidmod' ]);
        $consumer2->setIterations(1)->setDelay(0);
        $consumer2->handle($this->persistMessage(...));

        $this->assertNull($this->called);

        $this->assertCount(2, $stream->getConsumerNames());

        $this->client = null;

        # consumers removing process takes some time
        for ($i = 1; $i <= 30; $i++) {
            sleep(1);

            $stream = $this->getClient()->getApi()->getStream('my_stream');
            if (count($stream->getConsumerNames()) === 0) {
                break;
            }

            $this->client = null;
        }

        $stream = $this->getClient()->getApi()->getStream('my_stream');
        $this->assertCount(0, $stream->getConsumerNames());
    }

    public function persistMessage(Payload $message)
    {
        $this->called = $message->isEmpty() ? null : $message;
    }

    public function testBatching()
    {
        $client = $this->createClient();
        $stream = $client->getApi()->getStream('tester_' . rand(111, 999));
        $name = $stream->getConfiguration()->name;
        $stream->getConfiguration()->setSubjects([$name]);
        $stream->create();

        foreach (range(1, 10) as $tid) {
            $stream->put($name, ['tid' => $tid]);
        }

        $consumer = $stream->getConsumer('test');
        $consumer->getConfiguration()->setSubjectFilter($name);
        $consumer->setExpires(0.5);

        // [1] using 1 iteration
        $consumer->setIterations(1);
        $this->assertSame(1, $consumer->handle($this->persistMessage(...)));
        $this->assertNotNull($this->called);
        $this->assertSame($this->called->tid, 1);

        // [2], [3] using 2 iterations
        $consumer->setIterations(2);
        $this->assertSame(2, $consumer->handle($this->persistMessage(...)));
        $this->assertNotNull($this->called);
        $this->assertSame($this->called->tid, 3);

        // [4, 5] using 1 iteration
        $consumer->setBatching(2)->setIterations(1);
        $this->assertSame(2, $consumer->handle($this->persistMessage(...)));
        $this->assertNotNull($this->called);
        $this->assertSame($this->called->tid, 5);

        // [6, 7], [8, 9] using 2 iterations
        $consumer->setBatching(2)->setIterations(2);
        $this->assertSame(4, $consumer->handle($this->persistMessage(...)));

        // [10] using 1 iteration
        $consumer->setBatching(1)->setIterations(1);
        $this->assertSame(1, $consumer->handle($this->persistMessage(...)));

        // no more messages
        $consumer->setIterations(1);
        $this->assertSame(0, $consumer->handle($this->persistMessage(...)));
    }

    private function assertWrongNumPending(Consumer $consumer, ?int $expected = null, int $loops = 100): void
    {
        for ($i = 1; $i <= $loops; $i++) {
            $actual = $consumer->info()->getValue('num_pending');

            if ($actual === $expected) {
                break;
            }

            if ($i == $loops) {
                $this->assertSame($expected, $actual);
            }
        }
    }
}
