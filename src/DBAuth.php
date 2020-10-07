<?php

namespace Sim\Auth;

use PDO;
use Sim\Auth\Abstracts\AbstractAuth;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IAuthException;

class DBAuth extends AbstractAuth
{
    /**
     * @var PDO $pdo
     */
    protected $pdo;

    /**
     * DBAuth constructor.
     * @param PDO $pdo_instance
     * @param array $credentials
     * @param array|null $config
     * @param int $storage_type
     * @throws IAuthException
     */
    public function __construct(
        PDO $pdo_instance,
        array $credentials,
        ?array $config = null,
        int $storage_type = IAuth::STORAGE_COOKIE
    )
    {
        $this->pdo = $pdo_instance;
        parent::__construct($credentials, $config, $storage_type);
    }

    /**
     * {@inheritdoc}
     */
    public function login(array $credentials, string $extra_query = null)
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