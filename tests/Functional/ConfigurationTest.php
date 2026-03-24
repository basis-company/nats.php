<?php

declare(strict_types=1);

namespace Tests\Functional;

use Basis\Nats\Consumer\AckPolicy;
use Basis\Nats\Consumer\DeliverPolicy;
use Basis\Nats\Consumer\ReplayPolicy;
use Basis\Nats\Stream\DiscardPolicy;
use Basis\Nats\Stream\RetentionPolicy;
use Basis\Nats\Stream\StorageBackend;
use Tests\FunctionalTestCase;

class ConfigurationTest extends FunctionalTestCase
{
    public function testClientDelayConfiguration()
    {
        $client = $this->getClient();

        $delay = floatval(rand(1, 100));
        $client->setDelay($delay);
        $this->assertSame($delay, $client->configuration->getDelay());
    }

    public function testClientConfigurationOverride()
    {
        $this->assertSame($this->getConfiguration()->host, getenv('NATS_HOST'));
        $this->assertEquals($this->getConfiguration()->port, getenv('NATS_PORT'));
    }

    public function testStreamConfgurationInvalidStorageBackend()
    {
        $this->expectExceptionMessage("Invalid storage backend");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setStorageBackend('s3');
    }

    public function testStreamConfgurationInvalidRetentionPolicy()
    {
        $this->expectExceptionMessage("Invalid retention policy");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setRetentionPolicy('lucky');
    }

    public function testStreamConfgurationInvalidDiscardPolicy()
    {
        $this->expectExceptionMessage("Invalid discard policy");
        $this->createClient()
            ->getApi()
            ->getStream('tester')
            ->getConfiguration()
            ->setDiscardPolicy('lucky');
    }

    public function testStreamConfigurationRestore(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('cfg_restore');
        
        $config = $stream->getConfiguration();
        $config->setSubjects(['test.subject.*', 'test.another.>'])
            ->setRetentionPolicy(RetentionPolicy::INTEREST)
            ->setDiscardPolicy(DiscardPolicy::NEW)
            ->setStorageBackend(StorageBackend::MEMORY)
            ->setReplicas(1)
            ->setMaxConsumers(100)
            ->setMaxAge(600_000_000_000)
            ->setMaxBytes(1024 * 1024)
            ->setMaxMessageSize(1024)
            ->setMaxMessagesPerSubject(1000)
            ->setDescription('Test stream for configuration restore')
            ->setDuplicateWindow(300.0)
            ->setAllowRollupHeaders(false)
            ->setDenyDelete(false)
            ->setAllowMsgSchedules(true);
        
        $stream->create();
        
        $restored = $api->getStream('cfg_restore')->getConfiguration();
        
        $this->assertSame('cfg_restore', $restored->getName());
        $this->assertSame(['test.subject.*', 'test.another.>'], $restored->getSubjects());
        $this->assertSame(RetentionPolicy::INTEREST, $restored->getRetentionPolicy());
        $this->assertSame(DiscardPolicy::NEW, $restored->getDiscardPolicy());
        $this->assertSame(StorageBackend::MEMORY, $restored->getStorageBackend());
        $this->assertSame(1, $restored->getReplicas());
        $this->assertSame(100, $restored->getMaxConsumers());
        $this->assertSame(600_000_000_000, $restored->getMaxAge());
        $this->assertSame(1024 * 1024, $restored->getMaxBytes());
        $this->assertSame(1024, $restored->getMaxMessageSize());
        $this->assertSame(1000, $restored->getMaxMessagesPerSubject());
        $this->assertSame('Test stream for configuration restore', $restored->getDescription());
        $this->assertSame(300.0, $restored->getDuplicateWindow());
        $this->assertFalse($restored->getAllowRollupHeaders());
        $this->assertFalse($restored->getDenyDelete());
        $this->assertTrue($restored->getAllowMsgSchedules());
    }

    public function testConsumerConfigurationRestore(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('consumer_cfg');
        $stream->getConfiguration()->setSubjects(['consumer.test.*']);
        $stream->create();
        
        $consumer = $stream->getConsumer('test_consumer');
        $config = $consumer->getConfiguration();
        $config->setAckPolicy(AckPolicy::ALL)
            ->setDeliverPolicy(DeliverPolicy::NEW)
            ->setReplayPolicy(ReplayPolicy::ORIGINAL)
            ->setAckWait(30_000_000_000)
            ->setMaxAckPending(100)
            ->setMaxDeliver(5)
            ->setIdleHeartbeat(5_000_000_000)
            ->setFlowControl(true)
            ->setHeadersOnly(true)
            ->setDeliverSubject('deliver.subject')
            ->setDeliverGroup('group1')
            ->setDescription('Test consumer')
            ->setSubjectFilter('consumer.test.*')
            ->setInactiveThreshold(60_000_000_000);
        
        $consumer->create();
        
        $restored = $stream->getConsumer('test_consumer')->getConfiguration();
        
        $this->assertSame('test_consumer', $restored->getName());
        $this->assertSame('consumer_cfg', $restored->getStream());
        $this->assertSame(AckPolicy::ALL, $restored->getAckPolicy());
        $this->assertSame(DeliverPolicy::NEW, $restored->getDeliverPolicy());
        $this->assertSame(ReplayPolicy::ORIGINAL, $restored->getReplayPolicy());
        $this->assertSame(30_000_000_000, $restored->getAckWait());
        $this->assertSame(100, $restored->getMaxAckPending());
        $this->assertSame(5, $restored->getMaxDeliver());
        $this->assertSame(5_000_000_000, $restored->getIdleHeartbeat());
        $this->assertTrue($restored->getFlowControl());
        $this->assertTrue($restored->getHeadersOnly());
        $this->assertSame('deliver.subject', $restored->getDeliverSubject());
        $this->assertSame('group1', $restored->getDeliverGroup());
        $this->assertSame('Test consumer', $restored->getDescription());
        $this->assertSame('consumer.test.*', $restored->getSubjectFilter());
        $this->assertSame(60_000_000_000, $restored->getInactiveThreshold());
        $this->assertFalse($restored->isEphemeral());
    }

    public function testEphemeralConsumerConfigurationRestore(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('ephemeral_cfg');
        $stream->getConfiguration()->setSubjects(['ephemeral.test.*']);
        $stream->create();
        
        $config = new \Basis\Nats\Consumer\Configuration($stream->getName());
        $config->ephemeral()
            ->setAckPolicy(AckPolicy::EXPLICIT)
            ->setDeliverPolicy(DeliverPolicy::LAST);
        
        $consumer = $stream->createEphemeralConsumer($config);
        $consumerName = $consumer->getName();
        
        $this->assertNotNull($consumerName);
        $this->assertNotSame('test_consumer', $consumerName);
        
        $restored = $stream->getConsumer($consumerName)->getConfiguration();
        
        $this->assertSame($consumerName, $restored->getName());
        $this->assertTrue($restored->isEphemeral());
        $this->assertSame(AckPolicy::EXPLICIT, $restored->getAckPolicy());
        $this->assertSame(DeliverPolicy::LAST, $restored->getDeliverPolicy());
    }

    public function testConsumerWithStartTime(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('start_time_cfg');
        $stream->getConfiguration()->setSubjects(['start.time.*']);
        $stream->create();
        
        $startTime = new \DateTime('2024-01-01T00:00:00Z');
        
        $consumer = $stream->getConsumer('start_time_consumer');
        $config = $consumer->getConfiguration();
        $config->setDeliverPolicy(DeliverPolicy::BY_START_TIME)
            ->setStartTime($startTime);
        
        $consumer->create();
        
        $restored = $stream->getConsumer('start_time_consumer')->getConfiguration();
        
        $this->assertSame(DeliverPolicy::BY_START_TIME, $restored->getDeliverPolicy());
        $this->assertEquals($startTime, $restored->getStartTime());
    }

    public function testConsumerWithStartSequence(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('start_seq_cfg');
        $stream->getConfiguration()->setSubjects(['start.seq.*']);
        $stream->create();
        
        $consumer = $stream->getConsumer('start_seq_consumer');
        $config = $consumer->getConfiguration();
        $config->setDeliverPolicy(DeliverPolicy::BY_START_SEQUENCE)
            ->setStartSequence(42);
        
        $consumer->create();
        
        $restored = $stream->getConsumer('start_seq_consumer')->getConfiguration();
        
        $this->assertSame(DeliverPolicy::BY_START_SEQUENCE, $restored->getDeliverPolicy());
        $this->assertSame(42, $restored->getStartSequence());
    }

    public function testConsumerWithSubjectFilters(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('filters_cfg');
        $stream->getConfiguration()->setSubjects(['filter.test.*']);
        $stream->create();
        
        $consumer = $stream->getConsumer('filters_consumer');
        $config = $consumer->getConfiguration();
        $config->setSubjectFilters(['filter.test.a', 'filter.test.b']);
        
        $consumer->create();
        
        $restored = $stream->getConsumer('filters_consumer')->getConfiguration();
        
        $this->assertSame(['filter.test.a', 'filter.test.b'], $restored->getSubjectFilters());
        $this->assertNull($restored->getSubjectFilter());
    }

    public function testStreamWithConsumerLimits(): void
    {
        $api = $this->createClient()->getApi();
        $stream = $api->getStream('limits_cfg');
        
        $config = $stream->getConfiguration();
        $config->setSubjects(['limits.test.*'])
            ->setConsumerLimits([
                'max_ack_pending' => 50,
                'inactive_threshold' => 30_000_000_000,
            ]);
        
        $stream->create();
        
        $restored = $api->getStream('limits_cfg')->getConfiguration();
        
        $this->assertSame([
            'max_ack_pending' => 50,
            'inactive_threshold' => 30_000_000_000,
        ], $restored->getConsumerLimits());
    }
}
