<?php

declare(strict_types=1);

namespace Tests\Unit;

use Basis\Nats\Configuration;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function testExponentialDelay()
    {
        $configuration = new Configuration([
            'delayMode' => Configuration::DELAY_EXPONENTIAL,
        ]);

        $this->assertSame($configuration->getDelayMode(), Configuration::DELAY_EXPONENTIAL);

        $start = microtime(true);
        $configuration->delay(0);
        $this->assertLessThan(0.01, microtime(true) - $start);
    }

    public function testInvalidDelayConfiguration()
    {
        $configuration = new Configuration();
        $this->expectExceptionMessage("Invalid mode: dreaming");
        $configuration->setDelay(1, 'dreaming');
    }

    public function testInvalidConfiguration()
    {
        $this->expectExceptionMessage("Invalid config option hero");
        new Configuration(['hero' => true]);
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
}
