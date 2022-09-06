<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Client;
use ReflectionProperty;
use Tests\FunctionalTestCase;

class ClientTest extends FunctionalTestCase
{
    public function test()
    {
        $this->assertTrue($this->createClient()->ping());
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

    public function testLazyConnection()
    {
        $this->createClient(['port' => -1]);
        $this->assertTrue(true);
    }

    public function testName()
    {
        $client = $this->createClient();
        $client->setName('name-test');
        $client->connect();
        $this->assertSame($client->connect->name, 'name-test');
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

        $this->assertTrue($client->info->tls_required);
        $this->assertTrue($client->info->tls_verify);
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
}
