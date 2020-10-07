<?php

namespace Sim\Auth\Interfaces;

interface IAuthVerifier
{
    /**
     * @param string $text
     * @param $algorithm
     * @return bool
     */
    public function verify(string $text, $algorithm): bool;
}