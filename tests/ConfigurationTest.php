<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Configuration;

class ConfigurationTest extends Test
{
    public function testClientConfigurationOverride()
    {
        $this->assertSame($this->getConfiguration()->host, getenv('NATS_HOST'));
        $this->assertEquals($this->getConfiguration()->port, getenv('NATS_PORT'));
    }

    public function testClientConfigurationToken()
    {
        $connection = new Configuration(['token' => 'zzz']);
        $this->assertArrayHasKey('auth_token', $connection->getOptions());
    }

    public function testClientConfigurationJwt()
    {
        $connection = new Configuration(['jwt' => random_bytes(16)]);
        $this->assertArrayHasKey('jwt', $connection->getOptions());
    }

    public function testClientConfigurationBasicAuth()
    {
        $connection = new Configuration(['user' => 'nekufa', 'pass' => 't0p53cr3t']);
        $this->assertArrayHasKey('user', $connection->getOptions());
        $this->assertArrayHasKey('pass', $connection->getOptions());
    }

    public function testStreamConfgurationInvalidStorageBackend()
    {
        $this->expectExceptionMessage("Invalid storage backend");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setStorageBackend('s3');
    }

    public function testStreamConfgurationInvalidRetentionPolicy()
    {
        $this->expectExceptionMessage("Invalid retention policy");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setRetentionPolicy('lucky');
    }

    public function testStreamConfgurationInvalidDiscardPolicy()
    {
        $this->expectExceptionMessage("Invalid discard policy");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setDiscardPolicy('lucky');
    }
}
