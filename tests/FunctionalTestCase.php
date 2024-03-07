<?php

declare(strict_types=1);

namespace Tests;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Tests\Utils\Logger;

abstract class FunctionalTestCase extends TestCase
{
    use Logger;

    public function createClient(array ...$options): Client
    {
        $configuration = $this->getConfiguration(...$options);


        $logger = null;
        if (getenv('NATS_CLIENT_LOG')) {
            $logger = $this->getLogger();
        }

        return new Client($configuration, $logger);
    }

    protected $client = null;

    public function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    public function getConfiguration(array ...$options): Configuration
    {
        return new Configuration([
            'host' => getenv('NATS_HOST'),
            'port' => +getenv('NATS_PORT'),
            'timeout' => 0.5,
            'verbose' => getenv('NATS_CLIENT_VERBOSE') == '1',
        ], ...$options);
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
