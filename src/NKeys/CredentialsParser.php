<?php

declare(strict_types=1);

namespace Basis\Nats\NKeys;

/**
 * Parser for Credentials File containing JWT and NKey seed
 */
class CredentialsParser
{
    /**
     * @param string $text
     * @return array{nkey?: string, jwt?: string}
     */
    public function parse(string $text): array
    {
        $jwtMatches = [];
        $nkeyMatches = [];

        if (
            !preg_match($this->getRegex("NATS USER JWT"), $text, $jwtMatches) ||
            !preg_match($this->getRegex("USER NKEY SEED"), $text, $nkeyMatches)
        ) {
            return [];
        }

        return [
            'jwt' => preg_replace('/\s+/', '', $jwtMatches[1]),
            'nkey' => preg_replace('/\s+/', '', $nkeyMatches[1])
        ];
    }

    private function getRegex(string $name): string
    {
        $wrapper = [
            "-----BEGIN $name-----",
            "------END $name------",
        ];

        $begin = preg_quote($wrapper[0], '/');
        $end = preg_quote($wrapper[1], '/');

        return "/$begin((?s:.)+)$end/m";
    }

    /**
     * @param string $path
     * @return array{nkey?: string, jwt?: string}
     */
    public static function fromFile(string $path): array
    {
        $text = file_get_contents($path);
        $parser = new self();

        return $parser->parse($text);
    }
}
