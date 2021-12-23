<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Client;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use ReflectionProperty;

class StreamTest extends Test
{
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
        $api = $this->getClient()->getApi();
        $this->assertSame($api->getInfo()->streams, 0);

        $stream = $api->getStream('my_stream');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::WORK_QUEUE)
            ->setSubjects(['tester.greet', 'tester.bye']);

        $stream->create();

        $this->called = null;
        $consumer = $stream->getConsumer('my_consumer');
        $consumer->getConfiguration()->setSubjectFilter('tester.greet');
        $consumer->create();

        $this->assertNull($this->called);
        $consumer->handle($this->persistMessage(...), 1, 0);

        $this->assertNull($this->called);
        $stream->put('tester.greet', [ 'name' => 'nekufa' ]);
        $consumer->handle($this->persistMessage(...), 1, 0);

        $this->assertNotNull($this->called);
        $this->assertSame($this->called->name, 'nekufa');

        $this->called = null;
        $consumer = $stream->getConsumer('bye');
        $consumer->getConfiguration()->setSubjectFilter('tester.bye');
        $consumer->create();

        $stream->put('tester.greet', [ 'name' => 'nekufa' ]);
        $consumer->handle($this->persistMessage(...), 1, 0);

        $this->assertNull($this->called);
    }

    public function persistMessage($message)
    {
        $this->called = $message;
    }
}
