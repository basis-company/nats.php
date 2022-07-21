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
            "server_id" => "ID",
            "server_name" => "NAME",
            "version" => "1.0",
            "proto" => 1,
            "go" => "1",
            "host" => "host",
            "port" => 1234,
            "headers" => false,
            "domain" => "domain"
        ];
        $infoString = "INFO " . json_encode($infoData);
        
        $message = Factory::create($infoString);
        
        $this->assertEquals(Info::class, get_class($message));
    }
}
