<?php

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection\ServersManager;
use Tests\TestCase;
use ReflectionProperty;
use Tests\Utils\Logger;

class ConnectionTest extends TestCase
{
    use Logger;

    public function testDefaultConfig()
    {
        $client = $this->createClient([]);

        $this->assertTrue($client->ping());
    }


    public function testSingleServerEntry()
    {
        $client = $this->createClient([
            'servers' => ["localhost:4222"],
            'reconnect' => true,
            'reconnectTimeWait' => 50,
            'maxReconnectAttempts' => 10,
            'serversRandomize' => false
        ]);

        $this->assertTrue($client->ping());
    }

    public function testOnlyOneServerUp()
    {
        $client = $this->createClient([
            'servers' => ["localhost:3222", "localhost:3223", "localhost:3224", "localhost:4222" ],
            'reconnect' => true,
            'reconnectTimeWait' => 50,
            'maxReconnectAttempts' => 0,
            'serversRandomize' => false
        ]);

        $this->assertTrue($client->ping());
    }

    public function testReconnectsAttempts()
    {
        $property = new ReflectionProperty(Client::class, 'serversManager');
        $property->setAccessible(true);

        $manager = null;
        try {
            $client = $this->createClient([
                'servers' => ["localhost:3222", "localhost:3223", "localhost:3224" ],
                'reconnect' => true,
                'reconnectTimeWait' => 10,
                'maxReconnectAttempts' => 5,
                'serversRandomize' => false
            ]);

            /**
             * @var $manager ServersManager
             */
            $manager = $property->getValue($client);

            $this->assertFalse($manager->allExceededReconnectionAttempts());
            $this->assertEquals(-3, $manager->totalReconnectionAttempts());

            $client->ping();
        } catch (\Exception $e) {
        }

        $this->assertTrue($manager->allExceededReconnectionAttempts());
        $this->assertEquals(15, $manager->totalReconnectionAttempts());
    }


    public function testMaximumReconnectsExceeded()
    {
        $this->expectExceptionMessage("Connection error: maximum reconnect attempts exceeded for all servers.");
        $client = $this->createClient([
            'servers' => ["localhost:3222", "localhost:3223", "localhost:3224" ],
            'reconnect' => true,
            'reconnectTimeWait' => 50,
            'maxReconnectAttempts' => 10,
            'serversRandomize' => false
        ]);
        $client->ping();
    }


    /**
     * @throws \Exception
     */
    private function createClient(array $options): \Basis\Nats\Client
    {
        $logger = null;
        if (getenv('NATS_CLIENT_LOG')) {
            $logger = $this->getLogger();
        }

        return new Client(new Configuration($options), $logger);
    }
}
