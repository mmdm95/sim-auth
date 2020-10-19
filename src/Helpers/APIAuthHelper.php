<?php

namespace Sim\Auth\Helpers;

class APIAuthHelper
{
    /**
     * It'll return the api key
     *
     * @return string
     */
    public static function parse(): string
    {
        $header = isset($_SERVER['HTTP_X_API_KEY'])
            ? $_SERVER['HTTP_X_API_KEY']
            : '';

        return $header;
    }
}