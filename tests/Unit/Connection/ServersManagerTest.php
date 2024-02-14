<?php

namespace Tests\Unit\Connection;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection\ServerPool;
use Basis\Nats\Message\Info;
use Tests\TestCase;
use ReflectionProperty;

class ServersManagerTest extends TestCase
{
    /**
     * @throws \Exception
     */
    public function testBasicSetup()
    {
        $config = new Configuration(
            [
                "servers" => ["host1:4222", "host2:4222", "host3:4222", "host4:4222", "host5:4222"],
                'reconnect' => true,
                'reconnectTimeWait' => 0.010,
                'maxReconnectAttempts' => 3,
                'serversRandomize' => false
            ]
        );

        $manager = new ServerPool($config);

        //Initial Connections
        $this->assertTrue($manager->hasServers());
        $this->assertFalse($manager->allExceededReconnectionAttempts());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host3:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host4:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host5:4222", $manager->nextServer()->getConnectionString());

        $this->assertTrue($manager->hasServers());
        $this->assertFalse($manager->allExceededReconnectionAttempts());
        $this->assertEquals(0, $manager->totalReconnectionAttempts());

        //Reconnection Attempts
        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host3:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host4:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host5:4222", $manager->nextServer()->getConnectionString());

        $this->assertTrue($manager->hasServers());
        $this->assertFalse($manager->allExceededReconnectionAttempts());
        $this->assertEquals(5, $manager->totalReconnectionAttempts());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host3:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host4:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host5:4222", $manager->nextServer()->getConnectionString());

        $this->assertTrue($manager->hasServers());
        $this->assertFalse($manager->allExceededReconnectionAttempts());
        $this->assertEquals(10, $manager->totalReconnectionAttempts());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host3:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host4:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host5:4222", $manager->nextServer()->getConnectionString());

        $this->assertTrue($manager->hasServers());
        $this->assertTrue($manager->allExceededReconnectionAttempts());
        $this->assertEquals(15, $manager->totalReconnectionAttempts());
    }


    /**
     * @throws \Exception
     */
    public function testRandomize()
    {
        $config = new Configuration(
            [
                "servers" => ["host1:4222", "host2:4222", "host3:4222", "host4:4222", "host5:4222"],
                'serversRandomize' => false
            ]
        );

        $manager = new ServerPool($config);

        $this->assertTrue($manager->hasServers());

        $arr = $this->getServersArr($manager);

        $all = array_reduce($arr, function ($cur, $e) {
            return $cur . '-->' . $e->getConnectionString();
        });

        $this->assertEquals('-->host1:4222-->host2:4222-->host3:4222-->host4:4222-->host5:4222', $all);

        $config = new Configuration(
            [
                "servers" => ["host1:4222", "host2:4222", "host3:4222", "host4:4222", "host5:4222"],
                'serversRandomize' => true
            ]
        );

        $manager = new ServerPool($config);

        $this->assertTrue($manager->hasServers());

        $arr = $this->getServersArr($manager);

        $all = array_reduce($arr, function ($cur, $e) {
            return $cur . '-->' . $e->getConnectionString();
        });

        $this->assertNotEquals('-->host1:4222-->host2:4222-->host3:4222-->host4:4222-->host5:4222', $all);
    }

    /**
     * @throws \Exception
     */
    public function testReconnectionAttemptsExceeded()
    {
        $this->expectExceptionMessage("Connection error: maximum reconnect attempts exceeded for all servers.");

        $config = new Configuration(
            [
                "servers" => ["host1:4222", "host2:4222"],
                'serversRandomize' => false,
                'reconnect' => true,
                'reconnectTimeWait' => 0.010,
                'maxReconnectAttempts' => 2,
            ]
        );

        $manager = new ServerPool($config);

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());

        $this->assertEquals("host1:4222", $manager->nextServer()->getConnectionString());
        $this->assertEquals("host2:4222", $manager->nextServer()->getConnectionString());
    }


    /**
     * @throws \Exception
     */
    public function testHandleInfo()
    {
        $config = new Configuration(
            [
                "servers" => ["localhost:4222", "localhost:4221"]]
        );

        $manager = new ServerPool($config);

        $this->assertTrue($manager->hasServers());

        $info = new Info([
            'ldm'  => true,
            'connect_urls' => ["localhost:4222", "localhost:4220"]
            ]);

        $manager->processInfoMessage($info);

        $this->assertTrue($manager->hasServers());
    }


    /**
     * @throws \Exception
     */
    public function testEmptyServerArray()
    {
        $config = new Configuration([]);

        $manager = new ServerPool($config);

        $this->assertFalse($manager->hasServers());

        $this->assertEquals(0, $manager->totalReconnectionAttempts());
    }


    public function testInvalidServerHostPort1()
    {
        $this->expectExceptionMessage("Invalid server configuration: localhost:4222:8080");

        $config = new Configuration(
            [
                "servers" => ["localhost:4222:8080"]]
        );

        new ServerPool($config);
    }


    public function testInvalidServerHostPort2()
    {
        $this->expectExceptionMessage("Invalid server configuration: localhost");

        $config = new Configuration(
            [
                "servers" => ["localhost"]]
        );

        new ServerPool($config);
    }

    public function testInvalidServerHostPort3()
    {
        $this->expectExceptionMessage("Invalid server configuration: :");

        $config = new Configuration(
            [
                "servers" => [":"]]
        );

        new ServerPool($config);
    }

    public function testInvalidServerHostPort4()
    {
        $this->expectExceptionMessage("Invalid server configuration: localhost:port");

        $config = new Configuration(
            [
                "servers" => ["localhost:port"]]
        );

        new ServerPool($config);
    }

    private function getServersArr(ServerPool $manager): array
    {
        $property = new ReflectionProperty(ServerPool::class, 'serversIterator');
        $property->setAccessible(true);
        $iterator = $property->getValue($manager);

        return $iterator->getArrayCopy();
    }
}
