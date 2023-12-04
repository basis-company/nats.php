<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\SecretKey;
use InvalidArgumentException;
use Tests\TestCase;

class SecretKeyTest extends TestCase
{
    private static $VALID_SECRET_KEY_HEX = "05de91c9b25408111262d7f4aa769b6d0c83e796d18cc9e1ecd16cdaf573d0876dbdcb0a7b213d6c04f55b6436afaf224ee52fba6cc9ba4da573b13ba8102012";
    private static $VALID_VERIFYING_KEY_HEX = "6dbdcb0a7b213d6c04f55b6436afaf224ee52fba6cc9ba4da573b13ba8102012";
    private static $VALID_PUBLIC_KEY = "UBW33SYKPMQT23AE6VNWINVPV4RE5ZJPXJWMTOSNUVZ3CO5ICAQBEIPK";

    public function testConstructionWithInvalidPrivateKeyArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid secret key provided");
        new SecretKey("", "", 0);
    }

    public function testConstructionWithInvalidVerifyingKeyArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid verifying key provided");
        new SecretKey(hex2bin(self::$VALID_SECRET_KEY_HEX), "", 0);
    }

    public function testConstructionWithInvalidPrefixArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid seed prefix");
        new SecretKey(hex2bin(self::$VALID_SECRET_KEY_HEX), hex2bin(self::$VALID_VERIFYING_KEY_HEX), 42);
    }

    /**
     * @dataProvider invalidSeedProvider
     */
    public function testFromSeedWithInvalidArgument(string $seed)
    {
        $this->expectException(InvalidArgumentException::class);
        SecretKey::fromSeed($seed);
    }

    public function invalidSeedProvider(): array
    {
        return [
            ["XYAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME"],
            ["SQAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME"]
        ];
    }

    public function testFromSeed()
    {
        $seed = "SUAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME";
        $key = SecretKey::fromSeed($seed);

        $this->assertEquals(self::$VALID_SECRET_KEY_HEX, bin2hex($key->value));
        $this->assertEquals(self::$VALID_PUBLIC_KEY, $key->getPublicKey());
    }
}
