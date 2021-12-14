<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Client;
use ReflectionProperty;

class SubjectTest extends Test
{
    public function testPerformance()
    {
        $client = $this->createClient();
        $client->setLogger(null);

        $this->limit = 100_000;
        $this->counter = 0;

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
            $client->processMessage();
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

    public function testPublishSubscribe()
    {
        $this->tested = false;

        $client = $this->createClient();
        $client->subscribe('hello', function ($message) {
            $this->assertSame($message, 'tester');
            $this->tested = true;
        });

        $client->publish('hello', 'tester');
        $client->processMessage();

        $this->assertTrue($this->tested);
    }

    public function testProcessing()
    {
        $client = $this->createClient();
        $client->subscribe('hello.sync', $this->greet(...));
        $this->assertSame($client->dispatch('hello.sync', 'Dmitry'), 'Hello, Dmitry');
    }

    public function testRequestResponse()
    {
        $client = $this->createClient();

        $client->subscribe('hello.request', $this->greet(...));

        $client->request('hello.request', 'Nekufa1', function ($response) {
            $this->assertEquals($response, 'Hello, Nekufa1');
        });

        $client->request('hello.request', 'Nekufa2', function ($response) {
            $this->assertEquals($response, 'Hello, Nekufa2');
        });

        // process request
        $client->processMessage();
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

    public function greet(string $name): string
    {
        return 'Hello, ' . $name;
    }
}
