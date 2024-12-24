<?php

namespace Basis\Nats\Service;

use Basis\Nats\Client;
use Basis\Nats\Service\Response\Info;
use Basis\Nats\Service\Response\Ping;
use Basis\Nats\Service\Response\Stats;

class Service
{
    private string $id;
    private string $started;

    /** @var array<ServiceEndpoint> */
    private array $endpoints = [];
    private array $groups = [];
    private array $subscriptions = [];

    public function __construct(
        public Client $client,
        private string $name,
        private string $description = 'Default Description',
        private string $version = '0.0.1'
    ) {
        $this->id = $this->generateId();
        $this->started = date("Y-m-d\TH:i:s.v\Z");
        $this->registerVerbs();
    }

    private function generateId(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $charactersLength = strlen($characters);
        $randomString = "";

        for ($i = 0; $i < 22; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public function info(): Info
    {
        return new Info(
            name: $this->name,
            id: $this->id,
            version: $this->version,
            description: $this->description,
            endpoints: $this->endpoints,
        );
    }

    public function ping(): Ping
    {
        return new Ping($this->name, $this->id, $this->version);
    }

    public function stats(): Stats
    {
        return new Stats(
            name: $this->name,
            id: $this->id,
            version: $this->version,
            started: $this->started,
            endpoints: $this->endpoints,
        );
    }

    public function addGroup(string $name): ServiceGroup
    {
        if (!array_key_exists($name, $this->groups)) {
            $this->groups[$name] = new ServiceGroup($this, $name);
        }

        return $this->groups[$name];
    }

    public function addEndpoint(
        string $name,
        string|EndpointHandler|callable $endpointHandler,
        array $options = []
    ): void {
        $subject = $name;
        $queue_group = 'q';

        if (array_key_exists('subject', $options)) {
            $subject = $options['subject'];
        }

        if (array_key_exists('queue_group', $options)) {
            $queue_group = $options['queue_group'];
        }

        if (array_key_exists($name, $this->endpoints)) {
            throw new \LogicException("Endpoint $name already is defined");
        }

        $this->endpoints[$name] = new ServiceEndpoint($this, $name, $subject, $endpointHandler, $queue_group);
    }

    public function reset(): void
    {
        array_map(
            function (ServiceEndpoint $endpoint) {
                $endpoint->resetStats();
            },
            $this->endpoints
        );
    }

    private function registerVerbs(): void
    {
        foreach (['ping', 'info', 'stats'] as $verb) {
            $handler = function () use ($verb) {
                return (array) $this->$verb();
            };
            $verb = strtoupper($verb);
            // Add the all handler
            $this->addInternalHandler($verb, '', '', "$verb-all", $handler);
            // Add the kind handler
            $this->addInternalHandler($verb, $this->name, '', "$verb-kind", $handler);
            // Add the service id handler
            $this->addInternalHandler($verb, $this->name, $this->id, $verb, $handler);
        }
    }

    private function addInternalHandler(
        string $verb,
        string $kind,
        string $id,
        string $name,
        callable $handler
    ): void {
        $subject = $this->controlSubject($verb, $kind, $id);

        $this->subscriptions[$name] = $this->client->subscribe(
            $subject,
            $handler
        );
    }

    private function controlSubject(string $verb, string $name, string $id): string
    {
        if ($name == '' && $id == '') {
            return "\$SRV.$verb";
        }

        if ($id == '') {
            return "\$SRV.$verb.$name";
        }

        return "\$SRV.$verb.$name.$id";
    }

    public function run(?float $timeout = null): void
    {
        $this->client->logger->info("$this->name is ready to accept connections\n");
        $start = microtime(true);

        while ($timeout ? microtime(true) < $start + $timeout : true) {
            try {
                $this->client->process();
            } catch (\Exception $e) {
                $this->client
                    ->logger
                    ->error("$this->name encountered an error:\n" . $e->getMessage() . "\n");
            }
        }
    }
}
