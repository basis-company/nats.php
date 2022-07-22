<?php

declare(strict_types=1);

namespace Tests\Unit\NKeys;

use Basis\Nats\NKeys\CredentialsParser;
use Tests\TestCase;

class CredentialsParserTest extends TestCase
{
    public function testParse()
    {
        $text = <<<CREDS
-----BEGIN NATS USER JWT-----
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
------END NATS USER JWT------

************************* IMPORTANT *************************
NKEY Seed printed below can be used to sign and prove identity.
NKEYs are sensitive and should be treated as secrets.

-----BEGIN USER NKEY SEED-----
SUAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME
------END USER NKEY SEED------

*************************************************************
CREDS;

        $expected = [
            'jwt' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
            'nkey' => 'SUAALXURZGZFICARCJRNP5FKO2NW2DED46LNDDGJ4HWNC3G26VZ5BBZAME'
        ];

        $parser = new CredentialsParser();
        $result = $parser->parse($text);

        $this->assertEquals($expected, $result);
    }

    public function testParseEmpty()
    {
        $parser = new CredentialsParser();
        $result = $parser->parse("");

        $this->assertEquals([], $result);
    }
}
