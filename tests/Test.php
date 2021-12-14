<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

abstract class Test extends TestCase
{
    protected ?LoggerInterface $logger = null;

    public function getLogger(): LoggerInterface
    {
        if (!$this->logger) {
            $reflection = new ReflectionClass(get_class($this));
            $this->logger = new Logger($reflection->getShortName());
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        }
        return $this->logger;
    }

    public function createClient(): Client
    {
        if (getenv('NATS_CLIENT_LOG')) {
            $client = new Client($this->getConfiguration(), $this->getLogger());
        } else {
            $client = new Client($this->getConfiguration());
        }

        return $client;
    }

    protected ?Client $client = null;

    public function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    public function getConfiguration(array $options = []): Configuration
    {
        return new Configuration(array_merge($options, [
            'host' => getenv('NATS_HOST'),
            'port' => +getenv('NATS_PORT'),
        ]));
    }

    public function setup(): void
    {
        if (getenv('NATS_TEST_LOG')) {
            $this->getLogger();
        }
    }

    public function tearDown(): void
    {
        $api = $this->createClient()->getApi();

        foreach ($api->getStreamNames() as $name) {
            $api->getStream($name)->delete();
        }
    }
}
