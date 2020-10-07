<?php

namespace Sim\Auth;

use Sim\Auth\Abstracts\AbstractAuth;

class DBAuth extends AbstractAuth
{
    /**
     * {@inheritdoc}
     */
    public function login(string $extra_query = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function loginWithID(int $id)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function logout()
    {
        return $this;
    }
}