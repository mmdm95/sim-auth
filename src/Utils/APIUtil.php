<?php

namespace Sim\Auth\Utils;

class APIUtil
{
    /**
     * @see https://www.php.net/manual/en/function.bin2hex.php#123711 - accept a suggestion
     * @return string
     * @throws \Exception
     */
    public static function generateAPIKey(): string
    {
        $bytes = function_exists('random_bytes')
            ? random_bytes(32)
            : openssl_random_pseudo_bytes(32);
        return bin2hex($bytes);
    }
}