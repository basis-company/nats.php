<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class SubjectTest extends FunctionalTestCase
{
    private bool $tested = false;
    private int $responseCounter = 0;
    private $socket;

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

    public function testRequestReplyToDefault()
    {
        $refSubscriptions = new ReflectionProperty(Client::class, 'subscriptions');
        $refSubscriptions->setAccessible(true);

        $client = $this->createClient();

        //subscriptions should be empty to begin with
        $this->assertEquals(0, count($refSubscriptions->getValue($client)));

        $client->subscribe('hello.request', $this->greet(...));
        $this->responseCounter = 0;

        //Adding one subscription for hello.request
        $this->assertEquals(1, count($refSubscriptions->getValue($client)));

        $client->request('hello.request', 'Nekufa1', function ($response) use ($client) {
            $this->assertEquals($response->body, 'Hello, Nekufa1');
            $this->responseCounter++;
        });

        //Added second subscription for reply message
        $this->assertEquals(2, count($refSubscriptions->getValue($client)));
        $this->assertStringStartsWith('_INBOX.', $refSubscriptions->getValue($client)[1]['name']);

        // processing requests
        // handler 1 was called during request 2
        $this->assertEquals(0, $this->responseCounter);

        // get request response
        $client->process(1);

        $this->assertEquals(1, $this->responseCounter);

        //back down to one subscription when reply is received
        $this->assertEquals(1, count($refSubscriptions->getValue($client)));
    }

    public function testRequestWithCustomReplyTo()
    {
        $property = new ReflectionProperty(Client::class, 'subscriptions');
        $property->setAccessible(true);

        $client = $this->createClient([
            'inboxPrefix' => '_MY_CUSTOM_PREFIX'
        ]);

        $client->subscribe('hello.request', $this->greet(...));
        $this->responseCounter = 0;

        $this->assertEquals(1, count($property->getValue($client)));

        $client->request('hello.request', 'Nekufa1', function ($response) use ($client) {
            $this->assertEquals($response->body, 'Hello, Nekufa1');
            $this->responseCounter++;
        });

        $this->assertEquals(2, count($property->getValue($client)));
        $this->assertStringStartsWith('_MY_CUSTOM_PREFIX.', $property->getValue($client)[1]['name']);

        // processing requests
        // handler 1 was called during request 2
        $this->assertEquals(0, $this->responseCounter);

        // get request response
        $client->process(1);

        $this->assertEquals(1, $this->responseCounter);

        $this->assertEquals(1, count($property->getValue($client)));
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
