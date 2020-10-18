<?php

namespace Sim\Auth\Interfaces;

interface IAuthValidator
{
    /**
     * @param array $credentials
     * @param string|null $extra_query
     * @param array $bind_values
     * @return bool
     */
    public function validate(array $credentials, string $extra_query = null, array $bind_values = []): bool;
}