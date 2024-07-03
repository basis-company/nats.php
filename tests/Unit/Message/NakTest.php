<?php

namespace Tests\Unit\Message;

use Basis\Nats\Message\Nak;
use Tests\TestCase;

class NakTest extends TestCase
{
    public function testNak()
    {
        $nak = new Nak([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0'
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  4\r\n-NAK", $nak->render());
    }

    public function testNakDelay()
    {
        $nak = new Nak([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0',
            'delay' => 10
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  26\r\n-NAK {\"delay\":10000000000}", $nak->render());
    }

    public function testNakFloatDelay()
    {
        $nak = new Nak([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0',
            'delay' => 1.1
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  25\r\n-NAK {\"delay\":1100000000}", $nak->render());
    }
    public function testNakZeroDelay()
    {
        $nak = new Nak([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0',
            'delay' => 0
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  4\r\n-NAK", $nak->render());
    }
}
