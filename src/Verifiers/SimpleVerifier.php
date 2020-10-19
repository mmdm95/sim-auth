<?php

namespace Sim\Auth\Verifiers;

use Sim\Auth\Interfaces\IAuthVerifier;

class SimpleVerifier implements IAuthVerifier
{
    /**
     * {@inheritdoc}
     */
    public function verify(string $text, string $hashed_value): bool
    {
        // if we don't have text or hashed value
        if ('' === trim($text) || '' === trim($hashed_value)) return false;

        return $text === $hashed_value;
    }
}