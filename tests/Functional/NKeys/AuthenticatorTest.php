<?php

declare(strict_types=1);

namespace Tests\Functional\NKeys;

use Basis\Nats\NKeys\CredentialsParser;
use Tests\FunctionalTestCase;

class AuthenticatorTest extends FunctionalTestCase
{
    public function testConnection()
    {
        $client = $this->createClient(
            [
                "port" => 4221
            ],
            $this->getCredentials()
        );

        $success = $client->ping();
        $this->assertTrue($success);
    }

    public function testFailedConnectionWithEmptyNKey()
    {
        $client = $this->createClient(
            [
                "port" => 4221
            ],
            $this->getCredentials(),
            [
                // Override nkey
                "nkey" => null
            ]
        );

        $this->expectExceptionMessage("Authorization Violation");
        $client->ping();
    }

    private function getCredentials(): array
    {
        $credentialPath = $this->getProjectRoot() . "/docker/credentials/keys/creds/user/user/user.creds";

        return CredentialsParser::fromFile($credentialPath);
    }
}
