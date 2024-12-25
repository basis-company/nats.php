<?php

declare(strict_types=1);

namespace Tests\Unit\Message;

use Basis\Nats\Client;
use Basis\Nats\Message\Ack;
use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
use Basis\Nats\Message\Msg;
use Basis\Nats\Message\Payload;
use Basis\Nats\Message\Pong;
use ReflectionProperty;
use Tests\TestCase;

class FactoryTest extends TestCase
{
    public function testInfo()
    {
        $infoData = [
            "headers" => false,
            "port" => 1234,
            "proto" => 1,
            "go" => "1",
            "host" => "host",
            "server_id" => "ID",
            "server_name" => "NAME",
            "version" => "1.0",
            "domain" => "domain"
        ];
        $infoString = "INFO " . json_encode($infoData);

        $message = Factory::create($infoString);

        $this->assertInstanceOf(Info::class, $message);
        $this->assertEquals(get_object_vars($message), $infoData);
        $this->assertSame($infoString, $message->render());
    }

    public function testMsgParseTrim()
    {
        $body = 'MSG {"length":3,"sid":"123","subject":"tester","hlength":null,"timestampNanos":null,"replyTo":null}';
        $this->assertSame(Msg::create("tester   123 3 ")->render(), $body);
    }

    public function testClientGetter()
    {
        $msg = Msg::create("a b c");
        $this->assertNull($msg->getClient());
        $client = new Client();
        $msg->setClient($client);
        $this->assertSame($client, $msg->getClient());
    }

    public function testInvalidMsgLength()
    {
        $this->expectExceptionMessage("Invalid Msg: a b");
        $this->assertSame(Msg::create("a b ")->subject, 'tester');
    }

    public function testPayloadValidation()
    {
        new Pong(new Payload(''));
        $this->expectExceptionMessage("Invalid property nick for message " . Pong::class);
        new Pong(new Payload(json_encode(['nick' => 'nekufa'])));
    }

    public function testRendering()
    {
        $this->assertSame("PONG", Factory::create("PONG")->render());
        $this->assertSame("+OK", Factory::create("+OK")->render());
    }

    public function testInvalidMessage()
    {
        $this->expectExceptionMessage("Parse message failure: TEST");
        Factory::create("TEST");
    }
}
