<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\AmpClient;
use Basis\Nats\Async\Socket;
use Basis\Nats\Client;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class ClientTest extends FunctionalTestCase
{
    /**
     * @dataProvider clientProvider
     */
    public function test(string $clientName)
    {
        $this->assertTrue($this->createClient(['client' => $clientName])->ping());
    }

    public function testConnectionTimeout(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Socket read timeout');

        $client = $this->createClient([
            'reconnect' => false,
        ]);
        $this->assertTrue($client->ping());

        $client->process(1);
    }

    public function testReconnect()
    {
        $client = $this->getClient();
        $this->assertTrue($client->ping());

        $property = new ReflectionProperty(Client::class, 'socket');
        $property->setAccessible(true);

        fclose($property->getValue($client));

        $this->assertTrue($client->ping());
    }

    public function testReconnectAmpClient()
    {
        /** @var AmpClient $client */
        $client = $this->createClient(['client' => AmpClient::class]);
        $client->connect();
        $property = new ReflectionProperty(AmpClient::class, 'socket');
        /** @var Socket $socket */
        $socket = $property->getValue($client);
        $socket->close();

        $this->assertTrue($client->ping());
    }

    /**
     * @dataProvider clientProvider
     */
    public function testLazyConnection(string $clientName)
    {
        $this->createClient(['port' => -1, 'client' => $clientName]);
        $this->assertTrue(true);
    }

    /**
     * @dataProvider clientProvider
     */
    public function testName(string $clientName)
    {
        $client = $this->createClient(['client' => $clientName]);
        $client->setName('name-test');
        $client->connect();
        $this->assertSame($client->connect->name, 'name-test');
    }

    /**
     * @dataProvider clientProvider
     */
    public function testInvalidConnection(string $clientName)
    {
        $this->expectExceptionMessageMatches('/^Connection refused$|^A connection attempt failed|^Invalid URI:.*/');
        $this->createClient(['port' => -1, 'client' => $clientName])->ping();
    }

    /**
     * @dataProvider clientProvider
     */
    public function testTLSConnection(string $clientName)
    {
        $client = $this->createClient([
            'client' => $clientName,
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCA.pem",
        ]);

        $this->assertTrue($client->ping());

        $this->assertTrue($client->info->tls_required);
        $this->assertTrue($client->info->tls_verify);
    }


    /**
     * @dataProvider clientProvider
     */
    public function testInvalidTlsRootCa(string $clientName)
    {
        $this->expectExceptionMessageMatches("/tlsCaFile file does not exist*|^TLS negotiation failed: failed loading cafile.*/");
        $client = $this->createClient([
            'client' => $clientName,
            'port' => 4220,
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCAWrong.pem",
        ]);
        $client->ping();
    }

    /**
     * @dataProvider clientProvider
     */
    public function testInvalidTlsCert(string $clientName)
    {
        $this->expectExceptionMessageMatches("/tlsCertFile file does not exist*|^TLS negotiation failed.*/");
        $client = $this->createClient([
            'client' => $clientName,
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert-wrong.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCAWrong.pem",
        ]);
        $client->ping();
    }

    /**
     * @dataProvider clientProvider
     */
    public function testInvalidTlsKey(string $clientName)
    {
        $this->expectExceptionMessageMatches("/tlsKeyFile file does not exist*|TLS negotiation failed.*/");
        $client = $this->createClient([
            'port' => 4220,
            'tlsCertFile' => $this->getProjectRoot() . "/docker/certs/client-cert.pem",
            'tlsKeyFile'  => $this->getProjectRoot() . "/docker/certs/client-key-wrong.pem",
            'tlsCaFile'   => $this->getProjectRoot() . "/docker/certs/rootCA.pem",
            'client' => $clientName,
        ]);
        $client->ping();
    }
}
