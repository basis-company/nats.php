<?php

declare(strict_types=1);

namespace Tests;

use Basis\Nats\AmpClient;
use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Tests\Utils\Logger;

abstract class FunctionalTestCase extends TestCase
{
    use Logger;

    public function createClient(array ...$options): Client
    {
        $class = $options[0]['client'] ?? Client::class;
        unset($options[0]['client']);

        $configuration = $this->getConfiguration(...$options);

        $logger = null;
        if (getenv('NATS_CLIENT_LOG')) {
            $logger = $this->getLogger();
        }

        return new $class($configuration, $logger);
    }

    protected ?Client $client = null;

    public function getClient(string $clientName = Client::class): Client
    {
        if($this->client instanceof $clientName) {
            return $this->client;
        }
        return $this->client ?? $this->createClient(['client' => $clientName]);
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

    public function clientProvider()
    {
        return [
            'ampClient' => [AmpClient::class],
            'client' => [Client::class],
        ];
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
