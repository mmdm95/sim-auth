<?php

namespace Sim\Auth\Helpers;

class BasicAuth
{
    /**
     * Returned array will be in below format:
     * [
     *   'username' => username from basic auth,
     *   'password' => the password from basic auth
     * ]
     *
     * NOTE:
     *   The value of authentication will be decode from base64
     *
     * @return array
     */
    public static function parse(): array
    {
        $header = isset($_SERVER['HTTP_AUTHORIZATION'])
            ? $_SERVER['HTTP_AUTHORIZATION']
            : '';

        if (strtolower(substr($header, 0, 6)) !== 'basic ') {
            return [
                'username' => '',
                'password' => '',
            ];
        }

        $encoded = substr($header, 6);
        $decoded = base64_decode($encoded);
        $arr = explode(':', $decoded);
        return [
            'username' => $arr[0] ?? '',
            'password' => $arr[1] ?? '',
        ];
    }
}