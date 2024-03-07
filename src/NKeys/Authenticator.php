<?php

declare(strict_types=1);

namespace Basis\Nats\NKeys;

use Basis\Nats\Authenticator as AuthenticatorInterface;

class Authenticator extends AuthenticatorInterface
{
    private $key;
    public function __construct(SecretKey $key)
    {
        $this->key = $key;

    }

    public function sign(string $nonce): string
    {
        $signature = sodium_crypto_sign_detached($nonce, $this->key->value);

        return base64_encode($signature);
    }

    public function getPublicKey(): string
    {
        return $this->key->getPublicKey();
    }
}
