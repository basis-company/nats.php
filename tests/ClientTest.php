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
}
