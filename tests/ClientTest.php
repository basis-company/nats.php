<?php

declare(strict_types=1);

namespace Basis\Nats\Tests;

use Basis\Nats\Configuration;

class ClientTest extends Test
{
    public function test()
    {
        $this->assertTrue($this->createClient()->ping());
    }

    public function testLazyConnection()
    {
        $this->createClient(['port' => -1]);
        $this->assertTrue(true);
    }

    public function testInvalidConnection()
    {
        $this->expectExceptionMessage("Connection refused");
        $this->createClient(['port' => -1])->ping();
    }
}
