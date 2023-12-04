<?php

declare(strict_types=1);

namespace Basis\Nats;

use Basis\Nats\NKeys\Authenticator as NKeysAuthenticator;
use Basis\Nats\NKeys\SecretKey;

abstract class Authenticator
{
    abstract public function sign(string $nonce): string;
    abstract public function getPublicKey(): string;

    public static function create(Configuration $configuration): ?self
    {
        if ($configuration->nkey) {
            $key = SecretKey::fromSeed($configuration->nkey);
            return new NKeysAuthenticator($key);
        }

        return null;
    }
}
