<?php

namespace Sim\Auth\Verifiers;

use Sim\Auth\Interfaces\IAuthVerifier;

class Verifier implements IAuthVerifier
{
    /**
     * @var string|int
     */
    protected $algo;

    /**
     * Verifier constructor.
     * @param $algo
     */
    public function __construct($algo)
    {
        $this->algo = $algo;
    }

    /**
     * {@inheritdoc}
     */
    public function verify(string $text, string $hashed_value): bool
    {
        if (is_string($this->algo)) {
            return hash($this->algo, $text) === $hashed_value;
        } else {
            return password_verify($text, $hashed_value);
        }
    }
}