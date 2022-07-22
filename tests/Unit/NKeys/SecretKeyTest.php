<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\SecretKey;
use Tests\TestCase;

class SecretKeyTest extends TestCase
{
    public function testFromSeed()
    {
        $seed = "SUAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME";
        $key = SecretKey::fromSeed($seed);

        $expectedSecretKey = "05de91c9b25408111262d7f4aa769b6d0c83e796d18cc9e1ecd16cdaf573d0876dbdcb0a7b213d6c04f55b6436afaf224ee52fba6cc9ba4da573b13ba8102012";

        $this->assertEquals($expectedSecretKey, bin2hex($key->value));
    }
}
