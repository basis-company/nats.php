<?php

declare(strict_types=1);

namespace Basis\Nats\NKeys;

class SecretKey
{
    public function __construct(public readonly string $value) {
        if (strlen($this->value) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
           throw new \InvalidArgumentException("Invalid secret key provided");
        }
    }

    public static function fromSeed(string $seed): self
    {
        $decoded = (new Base32Decoder())->decode($seed);
        $rawSeed = substr($decoded, 2, -2);

        // Deterministically derive the key pair from a single key
        $keyPair = sodium_crypto_sign_seed_keypair($rawSeed);

        // Extract the Ed25519 secret key from a keypair
        $secretKey = sodium_crypto_sign_secretkey($keyPair);

        return new self($secretKey);
    }
}
