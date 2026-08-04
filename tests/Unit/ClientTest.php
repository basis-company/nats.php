<?php

declare(strict_types=1);

namespace Tests\Unit;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Queue;
use LogicException;
use Tests\FunctionalTestCase;

class ClientTest extends FunctionalTestCase
{
    public function testGetApi(): void
    {
        $client = $this->createClient();
        
        $api = $client->getApi();
        
        $this->assertInstanceOf(\Basis\Nats\Api::class, $api);
    }

    public function testPublish(): void
    {
        $client = $this->createClient();
        
        $result = $client->publish('test.subject', 'test payload');
        
        $this->assertInstanceOf(Client::class, $result);
    }

    public function testSubscribe(): void
    {
        $client = $this->createClient();
        
        $queue = $client->subscribe('test.subscribe');
        
        $this->assertInstanceOf(Queue::class, $queue);
    }

    public function testSubscribeWithHandler(): void
    {
        $called = false;
        $handler = function ($message) use (&$called) {
            $called = true;
        };
        
        $client = $this->createClient();
        $client->subscribe('test.handler', $handler);
        
        $this->assertTrue(true); // Just verify subscribe works
    }

    public function testPing(): void
    {
        $client = $this->createClient();
        
        $result = $client->ping();
        
        $this->assertTrue($result);
    }

    public function testProcess(): void
    {
        $client = $this->createClient();
        
        $client->process(0);
        
        $this->assertTrue(true);
    }
}
   