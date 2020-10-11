<?php

namespace Sim\Auth\Interfaces;

interface IAuthVerifier
{
    /**
     * @param string $text
     * @param string $hashed_value
     * @return bool
     */
    public function verify(string $text, string $hashed_value): bool;
}