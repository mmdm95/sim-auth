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
     * @var array
     */
    protected $built_in_algo = [
        PASSWORD_DEFAULT, PASSWORD_BCRYPT,
    ];

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
        // if we don't have text or hashed value
        if ('' === trim($text) || '' === trim($hashed_value)) return false;

        if (!in_array($this->algo, $this->built_in_algo) && is_string($this->algo)) {
            return hash($this->algo, $text) === $hashed_value;
        } else {
            return password_verify($text, $hashed_value);
        }
    }
}