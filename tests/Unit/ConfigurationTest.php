<?php

declare(strict_types=1);

namespace Tests\Unit;

use Basis\Nats\Configuration;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
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

    public function testServersOption()
    {
        $config = new Configuration(
            [
            "servers" => ["localhost:4222", "localhost:4221"]]
        );

        $this->assertEquals(["localhost:4222", "localhost:4221"], $config->servers);
    }

    public function testEmptyServersOption()
    {
        $config = new Configuration([]);

        $this->assertEquals([], $config->servers);
    }
}
