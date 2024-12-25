<?php

declare(strict_types=1);

namespace Tests\Unit\Message;

use Basis\Nats\Message\Factory;
use Basis\Nats\Message\Info;
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
