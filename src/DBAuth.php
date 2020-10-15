<?php

namespace Sim\Auth;

use PDO;
use Sim\Auth\Abstracts\AbstractAuth;
use Sim\Auth\Exceptions\IncorrectPasswordException;
use Sim\Auth\Exceptions\InvalidUserException;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IAuthVerifier;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Verifiers\Verifier;
use Sim\Crypt\Exceptions\CryptException;

class DBAuth extends AbstractAuth
{
    /**
     * @var IAuthVerifier
     */
    protected $verifier;

    /**
     * DBAuth constructor.
     * @param PDO $pdo_instance
     * @param string $namespace
     * @param array $crypt_keys
     * @param string|int $algo
     * @param int $storage_type
     * @param array|null $config
     * @throws Exceptions\InvalidStorageTypeException
     * @throws IDBException
     * @throws CryptException
     */
    public function __construct(
        PDO $pdo_instance,
        string $namespace = 'default',
        array $crypt_keys = [],
        $algo = PASSWORD_BCRYPT,
        int $storage_type = IAuth::STORAGE_DB,
        ?array $config = null
    )
    {
        parent::__construct(
            $pdo_instance,
            $namespace,
            $crypt_keys,
            $storage_type,
            $config
        );
        $this->verifier = new Verifier($algo);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws InvalidUserException
     * @throws IncorrectPasswordException
     */
    public function login(
        array $credentials,
        string $extra_query = null,
        array $bind_values = []
    )
    {
        // only login if status is not active
        if ($this->getStatus() === IAuth::STATUS_ACTIVE) return $this;
        // if there is something stored on device, then resume that user
        if (!$this->isExpired() && !is_null($this->storage->restore())) {
            $this->resume();
            return $this;
        };

        $userColumns = $this->config_parser->getTablesColumn($this->users_key);
        $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);

        $where = "{$userColumns['username']}=:__auth_username_value__";
        if (!empty($extra_query)) {
            $where .= " AND ({$extra_query})";
            $bind_values = array_merge($bind_values, [
                '__auth_username_value__' => $credentials['username'],
            ]);
        } else {
            $bind_values = [
                '__auth_username_value__' => $credentials['username'],
            ];
        }

        // get user from database
        $user = $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->users_key],
            $this->tables[$this->user_role_key],
            "{$this->db->quoteName($this->tables[$this->users_key])}.{$this->db->quoteName($userColumns['id'])}" .
            "=" .
            "{$this->db->quoteName($this->tables[$this->user_role_key])}.{$this->db->quoteName($userRoleColumns['user_id'])}",
            $where,
            $userColumns['password'],
            $bind_values
        );

        if (count($user) !== 1) {
            throw new InvalidUserException('User is not exists!');
        }

        $password = $user[0]['password'];

        // verify password with user's password in db
        $verified = $this->verifier->verify($credentials['password'], $password);

        if (!$verified) {
            throw new IncorrectPasswordException('Password is not correct!');
        }

        $this->storage->store($credentials);

        // set verifier for storage to check user on some occasions
        $this->storage->setVerifier($this->verifier);

        return $this;
    }
}