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
            $name = $reflection->getShortName();
            foreach (debug_backtrace() as $trace) {
                if ($trace['class'] == __CLASS__) {
                    continue;
                }
                $name .= '.' . $trace['function'];
                break;
            }
            $this->logger = new Logger($name);
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        }
        return $this->logger;
    }

    public function createClient(array $options = []): Client
    {
        $configuration = $this->getConfiguration($options);

        $logger = null;
        if (getenv('NATS_CLIENT_LOG')) {
            $logger = $this->getLogger();
        }

        return new Client($configuration, $logger);
    }

    protected ?Client $client = null;

    public function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    public function getConfiguration(array $options = []): Configuration
    {
        return new Configuration(array_merge([
            'host' => getenv('NATS_HOST'),
            'port' => +getenv('NATS_PORT'),
            'timeout' => 0.5,
            'verbose' => false,
        ], $options));
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

        $api->client->logger = null;
        foreach ($api->getStreamNames() as $name) {
            $api->getStream($name)->delete();
        }
    }
}
