<?php

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use Basis\Nats\Service\Service;
use Tests\FunctionalTestCase;
use Tests\Utils\TestEndpoint;

class ServiceTest extends FunctionalTestCase
{
    private bool $tested = false;

    private function createTestService(): Service
    {
        /** @var Client $client */
        $client = $this->createClient([
            'host' => 'hermes.internal'
        ]);

        /** @var Service $service */
        $service = $client->service(
            name: 'TestService',
            description: 'Service description',
            version: '1.0'
        );

        return $service;
    }
    public function testServiceRequestReplyString()
    {
        $service = $this->createTestService();

        $service
            ->addGroup('v1')
            ->addEndpoint(
                'test',
                TestEndpoint::class
            );

        $service->client->publish('v1.test', '');

        $response = $service->client->process(1);

        $this->assertTrue($response['success']);
    }

    public function testServiceRequestReplyCallable()
    {
        $service = $this->createTestService();

        $service
            ->addGroup('v1')
            ->addEndpoint(
                'test',
                function (Payload $payload) {
                    return [
                        'success' => true
                    ];
                }
            );

        $service->client->publish('v1.test', '');

        $response = $service->client->process(1);

        $this->assertTrue($response['success']);
    }

    public function testServiceRequestReplyClass()
    {
        $service = $this->createTestService();

        $service
            ->addGroup('v1')
            ->addEndpoint(
                'test',
                new TestEndpoint()
            );

        $service->client->publish('v1.test', '');

        $response = $service->client->process(1);

        $this->assertTrue($response['success']);
    }
}
