<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\CRC16;
use Tests\TestCase;

class CRC16Test extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testHash(array $input, int $expected)
    {
        $result = CRC16::hash($input);

        $this->assertEquals($expected, $result);
    }

    public function dataProvider(): array
    {
        return [
            [unpack('C*', hex2bin("6dbdcb0a7b213d6c04f55b6436afaf224ee52fba6cc9ba4da573b13ba8102012")), 38323],
            [unpack('C*', hex2bin("2bf5af21cc4d2f04b821e0773ca032e50134d4dc628e5e260c105db958a3ab97")), 49357],
        ];
    }
}
