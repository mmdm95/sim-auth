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
     * @param int $alg
     * @param int $storage_type
     * @param array|null $config
     * @throws Exceptions\InvalidStorageTypeException
     * @throws IDBException
     * @throws \Sim\Crypt\Exceptions\CryptException
     */
    public function __construct(
        PDO $pdo_instance,
        string $namespace = 'default',
        array $crypt_keys = [],
        $alg = PASSWORD_BCRYPT,
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
        $this->verifier = new Verifier($alg);
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

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function resume()
    {
        $restoredVal = $this->storage->restore();
        if (!empty($restoredVal)) {
            try {
                if ($this->evaluateStorageValue()) {
                    // activate status
                    $this->storage->setStatus(IAuth::STATUS_ACTIVE);
                }
            } catch (\Exception $e) {
                // do nothing for now
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function isLoggedIn(): bool
    {
        if (IAUTH::STATUS_ACTIVE !== $this->getStatus()) return false;

        try {
            return $this->evaluateStorageValue();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @return bool
     * @throws IDBException
     */
    private function evaluateStorageValue(): bool
    {
        $restoredVal = $this->storage->restore();
        if (empty($restoredVal)) return false;

        $userColumns = $this->config_parser->getTablesColumn($this->users_key);
        if ($this->getStorageType() === IAuth::STORAGE_DB) {
            $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
            $userSess = $this->db->getFrom(
                $this->tables[$this->sessions_key],
                "{$sessionColumns['uuid']}=:u",
                $sessionColumns['user_id'],
                [
                    'u' => $restoredVal['uuid'],
                ]
            );

            if (count($userSess) !== 1) return false;

            $userId = $userSess[0][$sessionColumns['user_id']];

            $where = "{$userColumns['id']}=:u";
            $bindValues = [
                'u' => $userId,
            ];
        } else {
            $where = "{$userColumns['username']}=:u";
            $bindValues = [
                'u' => $restoredVal['username'],
            ];
        }

        $user = $this->db->getFrom(
            $this->tables[$this->users_key],
            $where,
            $userColumns['password'],
            $bindValues
        );

        if (count($user) !== 1) return false;

        $password = $user[0][$userColumns['password']];

        // verify password with user's password in db
        $verified = $this->verifier->verify($this->$restoredVal['password'], $password);

        if ($verified) return true;

        return false;
    }
}