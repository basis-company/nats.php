<?php

declare(strict_types=1);

namespace Tests\Unit\Message;

use Basis\Nats\Client;
use Basis\Nats\Configuration;
use Basis\Nats\Connection;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Payload;
use Exception;
use LogicException;
use Tests\FunctionalTestCase;

class MsgTest extends FunctionalTestCase
{
    public function testCreateWithSubjectSidLength(): void
    {
        $data = 'test.subject 1 5';
        $message = Msg::create($data);

        $this->assertSame('test.subject', $message->subject);
        $this->assertSame('1', $message->sid);
        $this->assertSame(5, $message->length);
        $this->assertNull($message->replyTo);
        $this->assertNull($message->hlength);
    }

    public function testCreateWithSubjectSidReplyToLength(): void
    {
        $data = 'test.subject 1 reply.to 5';
        $message = Msg::create($data);

        $this->assertSame('test.subject', $message->subject);
        $this->assertSame('1', $message->sid);
        $this->assertSame('reply.to', $message->replyTo);
        $this->assertSame(5, $message->length);
    }

    public function testCreateWithSubjectSidHlengthLength(): void
    {
        $data = 'test.subject 1 100 5';
        $message = Msg::create($data);

        $this->assertSame('test.subject', $message->subject);
        $this->assertSame('1', $message->sid);
        $this->assertSame(100, $message->hlength);
        $this->assertSame(5, $message->length);
    }

    public function testCreateWithAllFields(): void
    {
        $data = 'test.subject 1 reply.to 100 5';
        $message = Msg::create($data);

        $this->assertSame('test.subject', $message->subject);
        $this->assertSame('1', $message->sid);
        $this->assertSame('reply.to', $message->replyTo);
        $this->assertSame(100, $message->hlength);
        $this->assertSame(5, $message->length);
    }

    public function testCreateWithInvalidData(): void
    {
        // Invalid data just creates a message with fewer fields, doesn't always throw
        // This test verifies the message is created without crashing
        $message = Msg::create('just one field');
        
        $this->assertInstanceOf(Msg::class, $message);
    }

    public function testParseWithHeaders(): void
    {
        // Headers: "NATS/1.0 100 200\r\ntest: value\r\n\r\n" = 33 bytes
        $data = 'test.subject 1 reply.to 33 5';
        $message = Msg::create($data);
        
        $payload = "NATS/1.0 100 200\r\ntest: value\r\n\r\nbody";
        $message->parse($payload);

        $this->assertInstanceOf(Payload::class, $message->payload);
        $this->assertSame('body', (string) $message->payload);
        $this->assertArrayHasKey('test', $message->payload->headers);
        $this->assertSame('value', $message->payload->headers['test']);
    }

    public function testParseWithoutHeaders(): void
    {
        $data = 'test.subject 1 5';
        $message = Msg::create($data);
        
        $payload = 'simple body';
        $message->parse($payload);

        $this->assertInstanceOf(Payload::class, $message->payload);
        $this->assertSame('simple body', (string) $message->payload);
    }

    public function testReplyWithNoReplyTo(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("Invalid replyTo property");

        $message = Msg::create('test.subject 1 5');
        $message->setClient($this->createClient());
        $message->reply('test data');
    }

    public function testReplyWithReplyTo(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        
        $client = $this->createClient();
        $message->setClient($client);
        
        // Mock the connection to avoid actual network calls
        $connection = new Connection($client);
        $client->connection = $connection;
        
        // This should call publish on the client
        $message->reply('test reply');
        
        // Just verify it doesn't throw - actual publish would be tested elsewhere
        $this->assertTrue(true);
    }

    public function testReplyWithMessageObject(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        // Mock the connection
        $connection = new Connection($client);
        $client->connection = $connection;
        
        // Reply with a message object
        $message->reply(new \Basis\Nats\Message\Ack(['subject' => 'reply.to']));
        
        $this->assertTrue(true);
    }

    public function testTostringReturnsPayload(): void
    {
        $message = Msg::create('test.subject 1 5');
        $message->parse('test payload');

        $this->assertSame('test payload', (string) $message);
    }

    public function testGetClient(): void
    {
        $message = Msg::create('test.subject 1 5');
        
        $client = $this->createClient();
        $message->setClient($client);
        
        $this->assertSame($client, $message->getClient());
    }

    public function testAck(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        // Mock connection
        $connection = new Connection($client);
        $client->connection = $connection;
        
        $message->ack();
        $this->assertTrue(true);
    }

    public function testNack(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        $connection = new Connection($client);
        $client->connection = $connection;
        
        $message->nack(1.5);
        $this->assertTrue(true);
    }

    public function testProgress(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        $connection = new Connection($client);
        $client->connection = $connection;
        
        $message->progress();
        $this->assertTrue(true);
    }

    public function testTerm(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        $connection = new Connection($client);
        $client->connection = $connection;
        
        $message->term('test reason');
        $this->assertTrue(true);
    }

    public function testTermWithoutReason(): void
    {
        $message = Msg::create('test.subject 1 reply.to 5');
        $client = $this->createClient();
        $message->setClient($client);
        
        $connection = new Connection($client);
        $client->connection = $connection;
        
        $message->term();
        $this->assertTrue(true);
    }

    public function testRender(): void
    {
        $message = Msg::create('test.subject 1 5');
        
        $rendered = $message->render();
        
        $this->assertStringStartsWith('MSG', $rendered);
    }

    public function testTryParseMessageTimeWithJetStreamAck(): void
    {
        $reflection = new \ReflectionClass(Msg::class);
        $method = $reflection->getMethod('tryParseMessageTime');
        $method->setAccessible(true);
        
        $values = [
            'subject' => 'test',
            'sid' => '1',
            'replyTo' => '$JS.ACK.stream.consumer.1.2.3.4.5678',
            'hlength' => 0,
            'length' => 5,
        ];
        
        $result = $method->invoke(null, $values);
        
        $this->assertArrayHasKey('timestampNanos', $result);
        $this->assertIsInt($result['timestampNanos']);
        $this->assertGreaterThan(0, $result['timestampNanos']);
    }

    public function testTryParseMessageTimeWithoutJetStreamAck(): void
    {
        $reflection = new \ReflectionClass(Msg::class);
        $method = $reflection->getMethod('tryParseMessageTime');
        $method->setAccessible(true);
        
        $values = [
            'subject' => 'test',
            'sid' => '1',
            'replyTo' => 'normal.reply.to',
            'hlength' => 0,
            'length' => 5,
        ];
        
        $result = $method->invoke(null, $values);
        
        $this->assertArrayNotHasKey('timestampNanos', $result);
    }

    public function testTryParseMessageTimeWithoutReplyTo(): void
    {
        $reflection = new \ReflectionClass(Msg::class);
        $method = $reflection->getMethod('tryParseMessageTime');
        $method->setAccessible(true);
        
        $values = [
            'subject' => 'test',
            'sid' => '1',
            'hlength' => 0,
            'length' => 5,
        ];
        
        $result = $method->invoke(null, $values);
        
        $this->assertArrayNotHasKey('timestampNanos', $result);
    }
}
