<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Message\Payload;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use DateTime;
use Tests\FunctionalTestCase;

class DeliveryPolicyTest extends FunctionalTestCase
{
    public function testDeliveryPolicyByStartSeq(): void
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $stream = $api->getStream('my_stream');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::LIMITS)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setSubjects(['tester.hi', 'tester.bye']);

        $stream->create();
        $stream->put('tester.hi', 'hi'); # this message will be skipped because of delivery policy
        $stream->put('tester.bye', 'bye');

        $consumer = $stream->getConsumer('my_consumer')
            ->setIterations(1)
            ->setBatching(1)
            ->setExpires(1.0);
        $consumer->getConfiguration()
            ->setDeliverPolicy(DeliverPolicy::BY_START_SEQUENCE)
            ->setStartSequence(2);
        $consumer->create();

        $handled = false;
        $consumer->handle(function (Payload $payload) use (&$handled) {
            $this->assertSame('tester.bye', $payload->subject);
            $this->assertSame('bye', $payload->body);
            $handled = true;
        });

        $this->assertTrue($handled, 'Message was not handled.');
    }

    public function testDeliveryPolicyByStartTime(): void
    {
        $api = $this->getClient()
            ->skipInvalidMessages(true)
            ->getApi();

        $stream = $api->getStream('my_stream');

        $stream->getConfiguration()
            ->setRetentionPolicy(RetentionPolicy::LIMITS)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setSubjects(['tester.hi']);

        $stream->create();
        $stream->put('tester.hi', 'hi'); # this message will be skipped because of delivery policy

        sleep(1); # sleep to have some difference between message timestamps
        $time = new DateTime();
        $stream->put('tester.hi', 'Hey!!!');
        $consumer = $stream->getConsumer('my_consumer')
            ->setIterations(1)
            ->setBatching(1)
            ->setExpires(1.0);
        $consumer->getConfiguration()
            ->setDeliverPolicy(DeliverPolicy::BY_START_TIME)
            ->setStartTime($time);
        $consumer->create();

        $handled = false;
        $consumer->handle(function (Payload $payload) use (&$handled) {
            $this->assertSame('tester.hi', $payload->subject);
            $this->assertSame('Hey!!!', $payload->body);
            $handled = true;
        });

        $this->assertTrue($handled, 'Message was not handled.');
    }
}
