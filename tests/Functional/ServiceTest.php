<?php

namespace Tests\Functional;

use Basis\Nats\Client;
use Basis\Nats\Message\Payload;
use Basis\Nats\Service\Service;
use Tests\FunctionalTestCase;
use Tests\Utils\TestEndpoint;

class ServiceTest extends FunctionalTestCase
{
    private function createTestService(): Service
    {
        /** @var Client $client */
        $client = $this->createClient();

        /** @var Service $service */
        $service = $client->service(
            name: 'TestService',
            description: 'Service description',
            version: '1.0'
        );

        return $service;
    }

    public function testServiceInfo()
    {
        $service = $this->createTestService();
        $service->addGroup('v1')->addEndpoint('test_info', TestEndpoint::class);

        $service->client->publish('$SRV.INFO', '');
        $info = $service->client->process(1);

        $this->assertIsArray($info);
        $this->assertArrayHasKey('type', $info);
        $this->assertSame($info['type'], 'io.nats.micro.v1.info_response');
        $this->assertSame($info['name'], 'TestService');

        $this->assertCount(1, $info['endpoints']);
        $this->assertSame("v1.test_info", $info['endpoints']['test_info']['subject']);
    }

    public function testServicePing()
    {
        $service = $this->createTestService();

        $service->addGroup('v1')->addEndpoint('test_ping', TestEndpoint::class);

        $service->client->publish('$SRV.PING', '');
        $ping = $service->client->process(1);

        $this->assertIsArray($ping);
        $this->assertArrayHasKey('type', $ping);
        $this->assertSame($ping['type'], 'io.nats.micro.v1.ping_response');
        $this->assertSame($ping['name'], 'TestService');
    }

    public function testServiceStats()
    {
        $service = $this->createTestService();

        $service->addGroup('v1')->addEndpoint('test_stats', TestEndpoint::class);

        $service->client->publish('$SRV.STATS', '');
        $stats = $service->client->process(1);

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('type', $stats);
        $this->assertSame($stats['type'], 'io.nats.micro.v1.stats_response');
        $this->assertSame($stats['name'], 'TestService');

        $this->assertSame($stats['endpoints'][0]['average_processing_time'], 0.0);

        $service->client->publish('v1.test_stats', '');
        $service->client->process(1);

        $service->client->publish('$SRV.STATS', '');
        $stats = $service->client->process(1);
        $this->assertNotSame($stats['endpoints'][0]['average_processing_time'], 0.0);
    }

    public function testServiceRequestReplyClass()
    {
        $service = $this->createTestService();

        $service->addGroup('v1')->addEndpoint('test_class', TestEndpoint::class);

        $service->client->publish('v1.test_class', '');
        $response = $service->client->process(1);
        $this->assertTrue($response['success']);
    }

    public function testServiceRequestReplyCallable()
    {
        $service = $this->createTestService();

        $service->addGroup('v1')->addEndpoint('test_callback', function (Payload $payload) {
            return [
                'success' => true,
                'nick' => $payload->getValue('nick'),
            ];
        });

        $service->client->publish('v1.test_callback', ['nick' => 'nekufa']);
        $response = $service->client->process(1);

        $this->assertTrue($response['success']);
        $this->assertSame($response['nick'], 'nekufa');
    }

    public function testServiceRequestReplyInstance()
    {
        $service = $this->createTestService();

        $service->addGroup('v1')->addEndpoint('test_instance', new TestEndpoint());

        $service->client->publish('v1.test_instance', '');
        $response = $service->client->process(1);
        $this->assertTrue($response['success']);
    }
}
