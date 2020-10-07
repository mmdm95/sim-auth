<?php

namespace Sim\Auth;

use Sim\Auth\Interfaces\IAuthVerifier;

class Verifier implements IAuthVerifier
{
    /**
     * {@inheritdoc}
     */
    public function verify(string $text, $algorithm): bool
    {

    }
}