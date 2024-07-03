<?php

namespace Tests\Unit\Message;

use Basis\Nats\Message\Ack;
use Tests\TestCase;

class AckTest extends TestCase
{
    public function testAck()
    {
        $ack = new Ack([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0'
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  0\r\n", $ack->render());
    }

}
