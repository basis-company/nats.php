<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\Base32;
use InvalidArgumentException;
use Tests\TestCase;

class Base32Test extends TestCase
{
    /**
     * @dataProvider dataProvider
     */
    public function testDecode(string $input, string $expected)
    {
        $base32 = new Base32();

        $result = $base32->decode($input);

        $this->assertEquals($expected, bin2hex($result));
    }

    /**
     * @dataProvider dataProvider
     */
    public function testEncode(string $expected, string $input)
    {
        $base32 = new Base32();

        $result = $base32->encode(hex2bin($input), false);

        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testEncodePadding(string $expected, string $input)
    {
        $base32 = new Base32();

        $result = $base32->encode(hex2bin($input));

        $this->assertEquals($expected . '======', $result);
    }

    /**
     * @dataProvider invalidInputProvider
     */
    public function testDecodeInvalid($input)
    {
        $base32 = new Base32();

        $this->expectException(InvalidArgumentException::class);
        $base32->decode($input);
    }

    public function invalidInputProvider(): array
    {
        return [
            ["aa?"],
            ["a======="],
            ["== =="]
        ];
    }

    public function testDecodeEmpty()
    {
        $base32 = new Base32();

        $this->assertEquals("", $base32->decode(""));
    }

    public function dataProvider(): array
    {
        return [
            ["SUAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME", "950005de91c9b25408111262d7f4aa769b6d0c83e796d18cc9e1ecd16cdaf573d0872061"],
            ["SUAHO4I62VWO2ECUBPHRU7BAB2BGQMVG4IILRL77TWSWT2SIMJCQHFVQPY", "950077711ed56ced10540bcf1a7c200e826832a6e210b8afff9da569ea4862450396b07e"],
        ];
    }
}
