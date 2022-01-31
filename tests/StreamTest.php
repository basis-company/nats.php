<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use ReflectionProperty;

class StreamTest extends Test
{
    public function testInterrupt()
    {
        $stream = $this->getClient()->getApi()->getStream('no_messages');
        $stream->getConfiguration()->setSubjects(['cucumber']);
        $stream->create();

        $consumer = $stream->getConsumer('test_consumer');
        $consumer->getConfiguration()->setSubjectFilter('cucumber');
        $consumer->setDelay(0)->create();

        $this->getClient()->publish('cucumber', 'message1');
        $this->getClient()->publish('cucumber', 'message2');
        $this->getClient()->publish('cucumber', 'message3');
        $this->getClient()->publish('cucumber', 'message4');

        $this->assertSame(4, $consumer->info()->getValue('num_pending'));

        $consumer->setBatching(1)->setIterations(2)
            ->handle(function ($response) use ($consumer) {
                $consumer->interrupt();
            });

        $this->assertSame(3, $consumer->info()->getValue('num_pending'));

        $consumer->setBatching(2)->setIterations(1)
            ->handle(function ($response) use ($consumer) {
                $consumer->interrupt();
            });

        $this->assertSame(1, $consumer->info()->getValue('num_pending'));
    }

    public function testNoMessages()
    {
        $this->called = false;
        $this->empty = false;

        $stream = $this->getClient()->getApi()->getStream('no_messages');
        $stream->getConfiguration()->setSubjects(['cucumber']);
        $stream->create();

        $consumer = $stream->getConsumer('test_consumer');
        $consumer->getConfiguration()->setSubjectFilter('cucumber');

        $consumer->create()
            ->setDelay(0)
            ->setIterations(1)
            ->handle(function ($response) {
                $this->called = $response;
            }, function () {
                $this->empty = true;
            });

        $this->assertFalse($this->called);
        $this->assertTrue($this->empty);
    }

    public function testSingletons()
    {
        $api = $this->getClient()->getApi();
        $this->assertSame($api, $this->getClient()->getApi());

        $stream = $api->getStream('tester');
        $this->assertSame($stream, $api->getStream('tester'));

        $consumer = $stream->getConsumer('worker');
        $this->assertSame($consumer, $stream->getConsumer('worker'));
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
        $consumer->setIterations(1);
        $consumer->handle($this->persistMessage(...));

        $this->assertNotNull($this->called);
        $this->assertSame($this->called->name, 'nekufa');

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
}
