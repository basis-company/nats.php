<?php

declare(strict_types=1);

namespace Tests\Unit\Message;

use Basis\Nats\Message\Payload;
use Tests\FunctionalTestCase;

class PayloadTest extends FunctionalTestCase
{
    public function testParseWithString(): void
    {
        $payload = Payload::parse('test data');

        $this->assertSame('test data', (string) $payload);
    }

    public function testParseWithPayload(): void
    {
        $original = new Payload('original');
        $result = Payload::parse($original);

        $this->assertSame($original, $result);
    }

    public function testParseWithArray(): void
    {
        $payload = Payload::parse(['key' => 'value']);

        $this->assertSame(json_encode(['key' => 'value']), (string) $payload);
    }

    public function testParseWithEmptyOrNull(): void
    {
        $payload = Payload::parse(null);

        $this->assertSame('', $payload->body);
    }

    public function testConstruct(): void
    {
        $payload = new Payload('body', ['X-Test' => 'value'], 'subject', 123456789);

        $this->assertSame('body', $payload->body);
        $this->assertArrayHasKey('X-Test', $payload->headers);
        $this->assertSame('value', $payload->headers['X-Test']);
        $this->assertSame('subject', $payload->subject);
        $this->assertSame(123456789, $payload->timestampNanos);
    }

    public function testRenderWithoutHeaders(): void
    {
        $payload = new Payload('hello');

        $rendered = $payload->render();

        $this->assertSame("5\r\nhello", $rendered);
    }

    public function testRenderWithHeaders(): void
    {
        $payload = new Payload('body', ['X-Test' => 'value']);

        $rendered = $payload->render();

        $this->assertStringContainsString('NATS/1.0', $rendered);
        $this->assertStringContainsString('X-Test: value', $rendered);
        $this->assertStringContainsString('body', $rendered);
    }

    public function testHasHeader(): void
    {
        $payload = new Payload('body', ['X-Test' => 'value']);

        $this->assertTrue($payload->hasHeader('X-Test'));
        $this->assertFalse($payload->hasHeader('X-NonExistent'));
    }

    public function testHasHeaders(): void
    {
        $payload1 = new Payload('body', ['X-Test' => 'value']);
        $payload2 = new Payload('body');

        $this->assertTrue($payload1->hasHeaders());
        $this->assertFalse($payload2->hasHeaders());
    }

    public function testGetHeader(): void
    {
        $payload = new Payload('body', ['X-Test' => 'value']);

        $this->assertSame('value', $payload->getHeader('X-Test'));
        $this->assertNull($payload->getHeader('X-NonExistent'));
    }

    public function testGetValues(): void
    {
        $payload = new Payload('{"key": "value"}');

        $this->assertIsObject($payload->getValues());
    }

    public function testGetValue(): void
    {
        $payload = new Payload('{"message": {"hdrs": "test-value"}}');

        $this->assertSame('test-value', $payload->getValue('message.hdrs'));
    }

    public function testGetValueNested(): void
    {
        $payload = new Payload('{"a": {"b": {"c": "deep"}}}');

        $this->assertSame('deep', $payload->getValue('a.b.c'));
    }

    public function testGetValueNonExistent(): void
    {
        $payload = new Payload('{"key": "value"}');

        $this->assertNull($payload->getValue('nonexistent'));
    }

    public function testIsEmpty(): void
    {
        $payload1 = new Payload('');
        $payload2 = new Payload('not empty');

        $this->assertTrue($payload1->isEmpty());
        $this->assertFalse($payload2->isEmpty());
    }

    public function testToString(): void
    {
        $payload = new Payload('test string');

        $this->assertSame('test string', (string) $payload);
    }

    public function testMagicGet(): void
    {
        $payload = new Payload('{"key": "value"}');

        $this->assertSame('value', $payload->key);
        $this->assertNull($payload->nonexistent);
    }
}
