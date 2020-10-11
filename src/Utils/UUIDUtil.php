<?php

namespace Sim\Auth\Utils;

class UUIDUtil
{
    /**
     * @see https://github.com/oittaa/uuid-php/blob/master/uuid.php
     * @param $hash
     * @param $version
     * @return string
     */
    private static function uuidFromHash($hash, $version): string
    {
        return sprintf('%08s-%04s-%04x-%04x-%12s',
            // 32 bits for "time_low"
            substr($hash, 0, 8),
            // 16 bits for "time_mid"
            substr($hash, 8, 4),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | $version << 12,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            // 48 bits for "node"
            substr($hash, 20, 12));
    }

    /**
     * @see https://github.com/oittaa/uuid-php/blob/master/uuid.php
     * @see https://www.php.net/manual/en/function.uniqid.php#94959
     * @return string
     * @throws \Exception
     */
    public static function v4(): string
    {
        $bytes = function_exists('random_bytes')
            ? random_bytes(16)
            : openssl_random_pseudo_bytes(16);
        $hash = bin2hex($bytes);
        return self::uuidFromHash($hash, 4);
    }

    /**
     * @see https://github.com/oittaa/uuid-php/blob/master/uuid.php
     * @see https://www.php.net/manual/en/function.uniqid.php#94959
     * @param $uuid
     * @return bool
     */
    public static function isValid($uuid): bool
    {
        return preg_match('/^(urn:)?(uuid:)?(\{)?[0-9a-f]{8}\-?[0-9a-f]{4}\-?' .
                '[0-9a-f]{4}\-?[0-9a-f]{4}\-?[0-9a-f]{12}(?(3)\}|)$/i', $uuid) === 1;
    }
}