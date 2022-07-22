<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\Authenticator;
use Basis\Nats\NKeys\SecretKey;
use Tests\TestCase;

class AuthenticatorTest extends TestCase
{
    public function testSign()
    {
        $key = new SecretKey(
            hex2bin("05de91c9b25408111262d7f4aa769b6d0c83e796d18cc9e1ecd16cdaf573d0876dbdcb0a7b213d6c04f55b6436afaf224ee52fba6cc9ba4da573b13ba8102012")
        );
        $authenticator = new Authenticator($key);

        $nonce = "bF-9WWCakwcGmbw";

        $expected = "64kIm1kvsUKHqLhIXoqFttoEsco1397GGfgnDuOK4xYx8Q+owv1pQa3oxNkCb/Ojc+F3Aw6hVr4f1t+PcR/7Bw==";
        $result = $authenticator->sign($nonce);

        $this->assertEquals($expected, $result);
    }
}
