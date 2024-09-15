<?php

namespace Basis\Nats\Service;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;

class Service
{
    public Client $client;

    private string $id;

    private string $name;

    private string $description = '';

    private string $version;

    private string $started;

    private array $endpoints = [];

    private array $groups = [];

    private array $subscriptions = [];

    public function __construct(
        Client $client,
        string $name,
        string $description = 'Default Description',
        string $version = '0.0.1'
    ) {
        $this->client = $client;
        $this->id = $this->generateId();
        $this->name = $name;
        $this->description = $description;
        $this->version = $version;
        $this->started = date("Y-m-d\TH:i:s.v\Z");

        // Register the service verbs to listen for
        $this->registerVerbs();
    }

    private function generateId(): string {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $charactersLength = strlen($characters);

        $randomString = "";

        for ($i = 0; $i < 22; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    private function ping(): array
    {
        return [
            'type' => 'io.nats.micro.v1.ping_response',
            'name' => $this->name,
            'id' => $this->id,
            'version' => $this->version,
        ];
    }

    private function info(): array
    {
        return [
            'type' => 'io.nats.micro.v1.info_response',
            'name' => $this->name,
            'id' => $this->id,
            'version' => $this->version,
            'description' => $this->description,
            'endpoints' => array_reduce($this->endpoints, function ($carry, $endpoint) {
                $carry[] = [
                    'name' => $endpoint->getName(),
                    'subject' => $endpoint->getSubject(),
                    'queue_group' => $endpoint->getQueueGroup(),
                ];

                return $carry;
            }, []),
        ];
    }

    private function stats(): array
    {
        return [
            'type' => 'io.nats.micro.v1.stats_response',
            'name' => $this->name,
            'id' => $this->id,
            'version' => $this->version,
            'endpoints' => array_reduce($this->endpoints, function ($carry, ServiceEndpoint $endpoint) {
                $carry[] = [
                    'name' => $endpoint->getName(),
                    'subject' => $endpoint->getSubject(),
                    'queue_group' => $endpoint->getQueueGroup(),
                    'num_requests' => $endpoint->getNumRequests(),
                    'num_errors' => $endpoint->getNumErrors(),
                    'last_error' => $endpoint->getLastError(),
                    'processing_time' => $endpoint->getProcessingTime(),
                    'average_processing_time' => $endpoint->getAverageProcessingTime(),
                ];

                return $carry;
            }, []),
            'started' => $this->started,
        ];
    }

    public function addGroup(string $name): ServiceGroup
    {
        $this->groups[$name] = new ServiceGroup($this, $name);

        return $this->groups[$name];
    }

    public function addEndpoint(
        string $name,
        EndpointHandler $endpointHandler,
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

        $this->endpoints[$name] = new ServiceEndpoint(
            $this,
            $name,
            $subject,
            $endpointHandler,
            $queue_group
        );
    }

    public function reset(): void
    {
        array_map(function (ServiceEndpoint $endpoint) {
            $endpoint->resetStats();
        }, $this->endpoints);
    }

    private function registerVerbs(): void
    {
        $verbs = [
            'PING' => function (Payload $payload) {
                return $this->ping();
            },
            'INFO' => function (Payload $payload) {
                return $this->info();
            },
            'STATS' => function (Payload $payload) {
                return $this->stats();
            },
        ];

        foreach ($verbs as $verb => $handler) {
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
}
