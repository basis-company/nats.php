<?php

namespace Tests\Unit\Message;

use Basis\Nats\Message\Progress;
use Tests\TestCase;

class ProgressTest extends TestCase
{
    public function testProgress()
    {
        $progress = new Progress([
            'subject' => '$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0'
        ]);

        $this->assertEquals("PUB \$JS.ACK.stream.consumer.1.3.18.1719992702186105579.0  4\r\n+WPI", $progress->render());
    }

}
