<?php

declare(strict_types=1);

namespace Tests\Functional;

use Tests\FunctionalTestCase;

class ConfigurationTest extends FunctionalTestCase
{
    public function testClientDelayConfiguration()
    {
        $client = $this->getClient();

        $delay = floatval(rand(1, 100));
        $client->setDelay($delay);
        $this->assertSame($delay, $client->configuration->getDelay());
    }

    public function testClientConfigurationOverride()
    {
        $this->assertSame($this->getConfiguration()->host, getenv('NATS_HOST'));
        $this->assertEquals($this->getConfiguration()->port, getenv('NATS_PORT'));
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
