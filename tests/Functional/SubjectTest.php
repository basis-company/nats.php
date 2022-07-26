<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class SubjectTest extends FunctionalTestCase
{
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
        $setter->call($client, $memoryStream);

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
}
