<?php

namespace Sim\Auth\Utils;

class AuthUtil
{
    /**
     * @param $time
     * @return int
     */
    public static function convertToIntTime($time): int
    {
        if (is_string($time)) {
            $time = strtotime($time);
        }
        if (!is_int($time)) {
            $time = 0;
        } else {
            if (!self::isValidTimestamp($time)) {
                $time = 0;
            }
            $time = 0 < (int)$time ? (int)$time : 0;
        }

        return (int)$time;
    }

    /**
     * @param $timestamp
     * @return bool
     */
    public static  function isValidTimestamp($timestamp): bool
    {
        return ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }

    /**
     * Retrieves the best guess of the client's actual IP address.
     * Takes into account numerous HTTP proxy headers due to variations
     * in how different ISPs handle IP addresses in headers between hops.
     *
     * @see https://stackoverflow.com/questions/1634782/what-is-the-most-accurate-way-to-retrieve-a-users-correct-ip-address-in-php
     * @return string
     */
    public static function getIPAddress(): string
    {
        foreach (array('HTTP_CLIENT_IP',
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'REMOTE_ADDR') as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip); // just to be safe

                    if (filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
                        ) !== false) {
                        return $ip;
                    }
                }
            }
        }
        return 'unknown';
    }
}