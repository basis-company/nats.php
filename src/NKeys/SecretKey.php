<?php

declare(strict_types=1);

namespace Basis\Nats\NKeys;

use InvalidArgumentException;

class SecretKey
{
    private const PREFIX_BYTE_SEED     = 18 << 3;
    private const PREFIX_BYTE_PRIVATE  = 15 << 3;
    private const PREFIX_BYTE_SERVER   = 13 << 3;
    private const PREFIX_BYTE_CLUSTER  = 2 << 3;
    private const PREFIX_BYTE_OPERATOR = 14 << 3;
    private const PREFIX_BYTE_ACCOUNT  = 0;
    private const PREFIX_BYTE_USER     = 20 << 3;

    private ?string $publicKey = null;

    public function __construct(
        public readonly string $value,
        private readonly string $verifyingKey,
        private readonly int $prefix
    ) {
        if (strlen($this->value) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new InvalidArgumentException("Invalid secret key provided");
        }
        if (strlen($this->verifyingKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new InvalidArgumentException("Invalid verifying key provided");
        }
        self::validatePrefix($this->prefix);
    }

    private static function validatePrefix(int $prefix): void
    {
        if (
            !in_array($prefix, [
                self::PREFIX_BYTE_SERVER,
                self::PREFIX_BYTE_CLUSTER,
                self::PREFIX_BYTE_OPERATOR,
                self::PREFIX_BYTE_ACCOUNT,
                self::PREFIX_BYTE_USER,
            ])
        ) {
            throw new InvalidArgumentException("Invalid seed prefix");
        }
    }

    public static function fromSeed(string $seed): self
    {
        $decoded = (new Base32())->decode($seed);

        // Validate seed
        $b1 = ord($decoded[0]) & 0xf8;
        $b2 = (ord($decoded[0]) & 7) << 5 | ((ord($decoded[1]) & 0xf8) >> 3);

        if ($b1 !== self::PREFIX_BYTE_SEED) {
            throw new InvalidArgumentException("Invalid seed");
        }
        self::validatePrefix($b2);

        // Deterministically derive the key pair from a single key
        $rawSeed = substr($decoded, 2, -2);
        $keyPair = sodium_crypto_sign_seed_keypair($rawSeed);

        // Extract the Ed25519 secret key from a keypair
        $secretKey = sodium_crypto_sign_secretkey($keyPair);

        // Extract the Ed25519 public key from a keypair
        $verifyingKey = sodium_crypto_sign_publickey($keyPair);

        return new self($secretKey, $verifyingKey, $b2);
    }

    public function getPublicKey(): string
    {
        if ($this->publicKey !== null) {
            return $this->publicKey;
        }
        // Bytearray with Ed25519 public key
        $verifyingKeyBytes = unpack('C*', $this->verifyingKey);

        // Prepending prefix byte
        array_unshift($verifyingKeyBytes, $this->prefix);

        // Calculating CRC16
        $crc = CRC16::hash($verifyingKeyBytes);
        // CRC16 int to bytes in little endian unsigned short
        $crcBytesLE = unpack('C*', pack('v', $crc));

        // Appending CRC16 LE to our bytearray
        $verifyingKeyBytes = array_merge($verifyingKeyBytes, $crcBytesLE);

        // Converting bytearray back to string
        $publicKeyString = call_user_func_array("pack", array_merge(["C*"], $verifyingKeyBytes));

        // Hashing public key as base32
        $this->publicKey = (new Base32())->encode($publicKeyString);
        return $this->publicKey;
    }
}
