<?php

declare(strict_types=1);

namespace Basis\Nats\NKeys;

use InvalidArgumentException;

class SecretKey
{
    private const PREFIX_BYTE_SEED     = 18 << 3;
    private const PREFIX_BYTE_PRIVATE  = 15 << 3;
    private const PREFIX_BYTE_SERVER   = 13 << 3;
    private const PREFIX_BYTE_CLUSTER  = 2  << 3;
    private const PREFIX_BYTE_OPERATOR = 14 << 3;
    private const PREFIX_BYTE_ACCOUNT  = 0;
    private const PREFIX_BYTE_USER     = 20 << 3;

    public function __construct(public readonly string $value)
    {
        if (strlen($this->value) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException("Invalid secret key provided");
        }
    }

    public static function fromSeed(string $seed): self
    {
        $decoded = (new Base32Decoder())->decode($seed);

        // Validate seed
        $b1 = ord($decoded[0]) & 0xf8;
        $b2 = (ord($decoded[0]) & 7) << 5 | ((ord($decoded[1]) & 0xf8) >> 3);

        if ($b1 !== self::PREFIX_BYTE_SEED) {
            throw new InvalidArgumentException("Invalid seed");
        } elseif (!in_array($b2, [
            self::PREFIX_BYTE_SERVER,
            self::PREFIX_BYTE_CLUSTER,
            self::PREFIX_BYTE_OPERATOR,
            self::PREFIX_BYTE_ACCOUNT,
            self::PREFIX_BYTE_USER,
        ])) {
            throw new InvalidArgumentException("Invalid seed prefix");
        }

        // Deterministically derive the key pair from a single key
        $rawSeed = substr($decoded, 2, -2);
        $keyPair = sodium_crypto_sign_seed_keypair($rawSeed);

        // Extract the Ed25519 secret key from a keypair
        $secretKey = sodium_crypto_sign_secretkey($keyPair);

        return new self($secretKey);
    }
}
