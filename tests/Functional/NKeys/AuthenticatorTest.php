<?php

declare(strict_types=1);

namespace Tests\Functional\NKeys;

use Basis\Nats\NKeys\CredentialsParser;
use Tests\FunctionalTestCase;

class AuthenticatorTest extends FunctionalTestCase
{
    public function testConnect()
    {
        $credentialPath = $this->getProjectRoot() . "/docker/credentials/keys/creds/user/user/user.creds";

        $client = $this->createClient(
            [
                "port" => 4221
            ],
            CredentialsParser::fromFile($credentialPath)
        );

        $client->connect();

        $success = $client->ping();
        $this->assertTrue($success);
    }
}
