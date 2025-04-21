<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Connection;
use Basis\Nats\Message\Payload;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class SubjectTest extends FunctionalTestCase
{
    private bool $tested = false;
    private int $responseCounter = 0;
    private $socket;

    public function testQueue()
    {
        $client = $this->createClient(['timeout' => 0.1]);

        $queue = $client->subscribe('handler');
        $queue->setTimeout(0.1);

        $client->publish('handler', 'tester');
        $client->logger?->info('published');
        $message = $queue->fetch(1);
        $this->assertNotNull($message);
        $this->assertSame("$message->payload", 'tester');

        $message = $queue->fetch(1);
        $this->assertNull($message);
        $this->assertCount(0, $queue->fetchAll(10));
        $this->assertCount(0, $queue->fetchAll(10));

        $client->publish('handler', 'tester1');
        $client->publish('handler', 'tester2');
        $this->assertCount(1, $queue->fetchAll(1));
        $this->assertCount(1, $queue->fetchAll(1));
        $this->assertCount(0, $queue->fetchAll(1));

        $client->publish('handler', 'tester3');
        $client->publish('handler', 'tester4');
        $this->assertCount(2, $queue->fetchAll(10));
        $this->assertCount(0, $queue->fetchAll(10));

        $client->publish('handler', 'tester5');
        $this->assertNotNull($queue->next());

        $this->expectExceptionMessage("Subject handler is empty");
        $queue->next(0.1);
    }

    public function testQueueUnsubscribe()
    {
        $client = $this->createClient(['timeout' => 0.1]);
        $queue = $client->subscribe('bazyaba');
        $this->assertCount(1, $client->getSubscriptions());
        $client->unsubscribe($queue);
        $this->assertCount(0, $client->getSubscriptions());
    }

    public function testPublishSubscribe()
    {
        $this->tested = false;

        $client = $this->createClient();
        $client->subscribe('hello', function ($message) {
            $this->assertSame('tester', $message->body);
            $this->assertSame('hello', $message->subject);
            $this->tested = true;
        });

        $client->publish('hello', 'tester');
        $client->process(1);

        $this->assertTrue($this->tested);
    }

    public function testSubscribeQueue()
    {
        $client = $this->createClient();

        $memoryStream = fopen('php://memory', 'w+');
        $setter = function ($socket) {
            $this->socket = $socket;
        };
        $setter->call($client->connection, $memoryStream);

        $client->subscribeQueue('subject', 'group', function () {
        });

        $content = stream_get_contents($memoryStream, -1, 0);

        $this->assertStringContainsString('SUB subject group', $content);
    }

    public function testProcessing()
    {
        $client = $this->createClient();
        $client->subscribe('hello.sync', $this->greet(...));
        $this->assertSame($client->dispatch('hello.sync', 'Dmitry')->body, 'Hello, Dmitry');
    }

    public function testRequestResponse()
    {
        $client = $this->createClient();

        $client->subscribe('hello.request', $this->greet(...));
        $this->responseCounter = 0;

        $client->request('hello.request', 'Nekufa1', function ($response) use ($client) {
            $this->assertEquals($response->body, 'Hello, Nekufa1');
            $this->responseCounter++;
        });

        $client->request('hello.request', 'Nekufa2', function ($response) use ($client) {
            $this->assertEquals($response->body, 'Hello, Nekufa2');
            $this->responseCounter++;
        });

        // processing requests
        // handler 1 was called during request 2
        $this->assertSame($this->responseCounter, 1);

        // process request 2
        $client->process(1);
        // get request 2 response
        $client->process(1);

        $this->assertSame($this->responseCounter, 2);
    }

    public function testUnsubscribe()
    {
        $property = new ReflectionProperty(Client::class, 'handlers');
        $property->setAccessible(true);

        $client = $this->createClient();

        $subjects = ['hello.request1', 'hello.request2'];
        foreach ($subjects as $subject) {
            $client->subscribe($subject, $this->greet(...));
        }
        $this->assertCount(2, $property->getValue($client));

        foreach ($subjects as $subject) {
            $client->unsubscribe($subject);
        }
        $this->assertCount(0, $property->getValue($client));
    }

    public function greet(Payload $payload): string
    {
        return 'Hello, ' . $payload->body;
    }

    public function testUnsubscribeAll(): void
    {
        $property = new ReflectionProperty(Client::class, 'handlers');
        $property->setAccessible(true);

        $client = $this->createClient();

        $subjects = ['hello.request1', 'hello.request2'];
        foreach ($subjects as $subject) {
            $client->subscribe($subject, $this->greet(...));
        }
        self::assertCount(2, $property->getValue($client));

        $client->unsubscribeAll();
        self::assertCount(0, $property->getValue($client));
    }

    public function testDisconnect(): void
    {
        $property = new ReflectionProperty(Client::class, 'handlers');
        $property->setAccessible(true);

        $client = $this->createClient();
        $connection = $client->connection;

        $subjects = ['hello.request1', 'hello.request2'];
        foreach ($subjects as $subject) {
            $client->subscribe($subject, $this->greet(...));
        }
        self::assertCount(2, $property->getValue($client));

        $client->disconnect();
        self::assertCount(0, $property->getValue($client));

        $property = new ReflectionProperty(Connection::class, 'socket');
        $property->setAccessible(true);

        // Assert that the socket is closed and set to null
        self::assertNull($property->getValue($connection));
    }
}
