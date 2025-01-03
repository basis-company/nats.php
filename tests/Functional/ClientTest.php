<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Connection;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class ClientTest extends FunctionalTestCase
{
    public function test()
    {
        $this->assertTrue($this->createClient()->ping());
    }

    public function testConnectionTimeout(): void
    {
        $client = $this->createClient([
            'reconnect' => false,
        ]);
        $this->assertTrue($client->ping());

        $property = new ReflectionProperty(Connection::class, 'socket');
        $property->setAccessible(true);
        fclose($property->getValue($client->connection));

        $this->expectExceptionMessage('supplied resource is not a valid stream resource');
        $client->process(1);
    }

    public function testReconnect()
    {
        $client = $this->getClient();
        $client->subscribe("tester");
        $this->assertTrue($client->ping());
        $this->assertCount(1, $client->getSubscriptions());

        $property = new ReflectionProperty(Connection::class, 'socket');
        $property->setAccessible(true);

        fclose($property->getValue($client->connection));

        $this->assertTrue($client->ping());
        $this->assertCount(1, $client->getSubscriptions());
    }

    public function testPacketSizeSetter()
    {
        $property = new ReflectionProperty(Connection::class, 'packetSize');
        $property->setAccessible(true);

        $client = $this->getClient();
        $client->connection->setPacketSize(512);
        $this->assertSame($property->getValue($client->connection), 512);
    }

    public function testLazyConnection()
    {
        $this->createClient(['port' => -1]);
        $this->assertTrue(true);
    }

    public function testName()
    {
        $client = $this->createClient();
        $client->setName('name-test');
        $client->ping();
        $this->assertSame($client->connection->getConnectMessage()->name, 'name-test');
    }

    public function testInvalidConnection()
    {
        $this->expectExceptionMessageMatches('/^Connection refused$|^A connection attempt failed/');
        $this->createClient(['port' => -1])->ping();
    }

    public function testTLSConnection()
    {
        $client = $this->createClient([
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCA.pem",
        ]);

        $this->assertTrue($client->ping());

        $this->assertTrue($client->connection->getInfoMessage()->tls_required);
        $this->assertTrue($client->connection->getInfoMessage()->tls_verify);
    }


    public function testInvalidTlsRootCa()
    {
        $this->expectExceptionMessageMatches("/tlsCaFile file does not exist*/");
        $client = $this->createClient([
            'port' => 4220,
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCAWrong.pem",
        ]);
        $client->ping();
    }

    public function testInvalidTlsCert()
    {
        $this->expectExceptionMessageMatches("/tlsCertFile file does not exist*/");
        $client = $this->createClient([
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert-wrong.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCAWrong.pem",
        ]);
        $client->ping();
    }

    public function testInvalidTlsKey()
    {
        $this->expectExceptionMessageMatches("/tlsKeyFile file does not exist*/");
        $client = $this->createClient([
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key-wrong.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCA.pem",
        ]);
        $client->ping();
    }

    public function testCloseClosesSocket(): void
    {
        $client = $this->createClient([]);
        self::assertTrue($client->ping());

        $connection = $client->connection;

        // Call the close method
        $connection->close();

        $property = new ReflectionProperty(Connection::class, 'socket');
        $property->setAccessible(true);

        // Assert that the socket is closed and set to null
        self::assertNull($property->getValue($connection));
    }
}
