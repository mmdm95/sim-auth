<?php

namespace Sim\Auth\Abstracts;

use PDO;
use Sim\Auth\Exceptions\InvalidStorageTypeException;
use Sim\Auth\Exceptions\InvalidUserException;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IAuthenticator;
use Sim\Auth\Interfaces\IAuthorizer;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Interfaces\IStorage;
use Sim\Auth\Storage\CookieStorage;
use Sim\Auth\Storage\DBStorage;
use Sim\Auth\Storage\SessionStorage;
use Sim\Auth\Utils\AuthUtil;
use Sim\Crypt\Exceptions\CryptException;

abstract class AbstractAuth extends AbstractBaseAuth implements
    IAuthenticator,
    IAuthorizer
{
    /**
     * @var array
     * Has following structure:
     * [
     *   'main' => contains main key,
     *   'assured' => contains assured key,
     * ]
     */
    protected $crypt_keys = [];

    /**
     * @var int $expire_time
     */
    protected $expire_time = 31536000 /* 1year */
    ;

    /**
     * @var int $suspend_time
     */
    protected $suspend_time = 1800 /* 30min */
    ;

    /**
     * @var int $storage_type
     */
    protected $storage_type = IAuth::STORAGE_DB;

    /**
     * @var string $namespace
     */
    protected $namespace = 'default';

    /**
     * @var IStorage|null
     */
    protected $storage = null;

    /********** table keys **********/

    /**
     * @var string
     */
    protected $users_key = 'users';

    /**
     * @var string
     */
    protected $user_role_key = 'user_role';

    /**
     * @var string
     */
    protected $user_res_perm_key = 'user_res_perm';

    /**
     * @var string
     */
    protected $sessions_key = 'sessions';

    /**
     * Note on input  parameters (Keys MUST be same as below):
     * $credentials:
     * [
     *   'username' => provided username by user,
     *   'password' => provided password by user,
     * ]
     * $crypt_keys:
     * [
     *   'main' => main key for crypt,
     *   'assured' => assured key for crypt,
     * ]
     *
     * Note:
     *  If you don't want to use CRYPT library,
     *  send an empty array as $crypt_keys parameter.
     *
     * AbstractAuth constructor.
     * @param PDO $pdo_instance
     * @param string $namespace
     * @param array $crypt_keys
     * @param int $storage_type
     * @param array|null $config
     * @throws CryptException
     * @throws IDBException
     * @throws InvalidStorageTypeException
     */
    public function __construct(
        PDO $pdo_instance,
        string $namespace = 'default',
        array $crypt_keys = [],
        int $storage_type = IAuth::STORAGE_DB,
        ?array $config = null
    )
    {
        parent::__construct($pdo_instance, $config);

        $this->crypt_keys = $crypt_keys;

        if (!$this->isValidStorageType($storage_type)) {
            throw new InvalidStorageTypeException(
                'Storage must be one of [db] or [cookie] or [session]. Use [IAuth] constants please'
            );
        } else {
            // actually it instantiate storage
            $this->setStorageType($storage_type);
        }

        // is must be at the end because storage instance
        // will create after setting namespace
        $this->setNamespace($namespace);

        // set status to active if there is a session/cookie
        // that is set before
        if (!is_null($this->storage->restore())) {
            $this->storage->setStatus(IAuth::STATUS_ACTIVE);
        }
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        $this->storage->hasExpired();
        $this->storage->hasSuspended();
        return $this->storage->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isLoggedIn(): bool
    {
        return IAUTH::STATUS_ACTIVE === $this->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired(): bool
    {
        return $this->storage->hasExpired()
            && IAuth::STATUS_EXPIRE === $this->storage->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isSuspended(): bool
    {
        return $this->storage->hasSuspended()
            && IAuth::STATUS_SUSPEND === $this->storage->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isNone(): bool
    {
        return IAuth::STATUS_NONE === $this->storage->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function extendSuspendTime()
    {
        $this->storage->updateSuspendTime();
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setExpiration($timestamp)
    {
        $this->expire_time = AuthUtil::convertToIntTime($timestamp);
        $this->storage->setExpireTime($this->expire_time);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiration(): int
    {
        return $this->expire_time;
    }

    /**
     * {@inheritdoc}
     */
    public function setSuspendTime($timestamp)
    {
        $this->suspend_time = AuthUtil::convertToIntTime($timestamp);
        $this->storage->setSuspendTime($this->suspend_time);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSuspendTime(): int
    {
        return $this->suspend_time;
    }

    /**
     * {@inheritdoc}
     * @throws CryptException
     * @throws IDBException
     */
    public function setStorageType(int $type)
    {
        if ($this->isValidStorageType($type)) {
            $this->storage_type = $type;
            $this->reInstantiateStorage();
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageType(): int
    {
        return $this->storage_type;
    }

    /**
     * {@inheritdoc}
     * @throws CryptException
     * @throws IDBException
     */
    public function setNamespace(string $namespace)
    {
        if (!empty($namespace)) {
            $this->namespace = $namespace;
            $this->reInstantiateStorage();
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws InvalidUserException
     */
    public function loginWithID(int $id)
    {
        $userColumns = $this->config_parser->getTablesColumn($this->users_key);
        $user = $this->getFromUsers(
            [
                $userColumns['username'],
                $userColumns['password'],
            ],
            "{$userColumns['id']}=:u_id",
            [
                'u_id' => $id,
            ]
        );

        if (count($user) !== 1) {
            throw new InvalidUserException('User is not exists!');
        }

        $this->storage->store($user[0]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function resume()
    {
        $this->storage->resume();
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function logout()
    {
        $res = false;
        if ($this->getStorageType() === IAuth::STORAGE_DB) {
            /**
             * @var DBStorage $storage
             */
            $storage = $this->storage;
            $res = $storage->destroy();
        }

        if ($this->getStorageType() !== IAuth::STORAGE_DB || $res) {
            $this->storage->delete();
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getSessionUUID($username = null): array
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return [];

        $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
        return $this->db->getFrom(
            $this->tables[$this->sessions_key],
            "{$this->db->quoteName($sessionColumns['user_id'])}=:u_id",
            $sessionColumns['uuid'],
            [
                'u_id' => $userId,
            ]
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function destroySession(string $session_uuid): bool
    {
        $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
        $this->db->delete(
            $this->tables[$this->sessions_key],
            "{$sessionColumns['uuid']}=:uuid",
            [
                'uuid' => $session_uuid,
            ]
        );
        return false;
    }

    /**
     * @return mixed
     */
    public function getCurrentUser(): ?array
    {
        $currentUser = $this->storage->restore();
        if (is_null($currentUser)) return null;
        return $currentUser;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function isAllow($resource, int $permission, $username = null): bool
    {
        try {
            if (!$this->isValidPermission($permission)) return false;

            // get user id
            $userId = $this->getUserID_($username);
            if (is_null($userId)) return false;

            // get resource id
            $resourceId = $this->getResourceID_($resource);
            if (is_null($resourceId)) return false;

            return $this->isAllow_($resourceId, $permission, $userId);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function allowUser($resource, array $permission, $username = null)
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return $this;

        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (is_null($resourceId)) return $this;

        $userResPermColumns = $this->config_parser->getTablesColumn($this->user_res_perm_key);
        foreach ($permission as $perm) {
            if (!$this->isValidPermission($perm)) continue;

            if (!$this->isAllow($resource, $perm, $username)) {

                $this->db->updateIfExistsOrInsert(
                    $this->tables[$this->user_res_perm_key],
                    [
                        $userResPermColumns['user_id'] => $userId,
                        $userResPermColumns['resource_id'] => $resourceId,
                        $userResPermColumns['perm_id'] => $perm,
                        $userResPermColumns['is_allow'] => 1,
                    ],
                    "{$this->db->quoteName($userResPermColumns['user_id'])}=:u_id " .
                    "AND {$this->db->quoteName($userResPermColumns['resource_id'])}=:rec_id " .
                    "AND {$this->db->quoteName($userResPermColumns['perm_id'])}=:perm_id",
                    [
                        'u_id' => $userId,
                        'rec_id' => $resourceId,
                        'perm_id' => $perm,
                    ]);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function disallowUser($resource, array $permission, $username = null)
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return $this;

        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (is_null($resourceId)) return $this;

        $userResPermColumns = $this->config_parser->getTablesColumn($this->user_res_perm_key);
        foreach ($permission as $perm) {
            if (!$this->isValidPermission($perm)) continue;

            if ($this->isAllow($resource, $perm, $username)) {
                $this->db->updateIfExistsOrInsert(
                    $this->tables[$this->user_res_perm_key],
                    [
                        $userResPermColumns['user_id'] => $userId,
                        $userResPermColumns['resource_id'] => $resourceId,
                        $userResPermColumns['perm_id'] => $perm,
                        $userResPermColumns['is_allow'] => 0,
                    ],
                    "{$this->db->quoteName($userResPermColumns['user_id'])}=:u_id " .
                    "AND {$this->db->quoteName($userResPermColumns['resource_id'])}=:rec_id " .
                    "AND {$this->db->quoteName($userResPermColumns['perm_id'])}=:perm_id",
                    [
                        'u_id' => $userId,
                        'rec_id' => $resourceId,
                        'perm_id' => $perm,
                    ]);
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getUserRole($username): array
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return [];

        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
        return $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->roles_key] . ' AS t1',
            $this->tables[$this->user_role_key] . ' AS t2',
            "t1.{$this->db->quoteName($roleColumns['id'])}=t2.{$this->db->quoteName($userRoleColumns['role_id'])}",
            "t2.{$this->db->quoteName($userRoleColumns['user_id'])}=:u_id",
            '*',
            [
                'u_id' => $userId,
            ]
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getCurrentUserRole(): array
    {
        return $this->getUserRole($this->getUserID_());
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function addRoleToUser(array $roles, $username = null)
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return $this;

        $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
        foreach ($roles as $r) {
            // get role id
            $roleId = $this->getRoleID_($r);
            if (is_null($roleId)) continue;

            // get user's role count
            $roleCount = $this->db->count(
                $this->tables[$this->user_role_key],
                "{$userRoleColumns['user_id']}=:u_id AND {$userRoleColumns['role_id']}=:r_id",
                [
                    'u_id' => $userId,
                    'r_id' => $roleId,
                ]
            );
            // add it to database if we have not that role there
            if (0 === $roleCount) {
                $this->db->insert(
                    $this->tables[$this->user_role_key],
                    [
                        $userRoleColumns['user_id'] => $userId,
                        $userRoleColumns['role_id'] => $roleId,
                    ]
                );
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function isAdmin($username = null): bool
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return false;

        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
        $userAdminRoles = $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->roles_key] . ' AS t1',
            $this->tables[$this->user_role_key] . ' AS t2',
            "t1.{$this->db->quoteName($roleColumns['id'])}=t2.{$this->db->quoteName($userRoleColumns['role_id'])}",
            "t2.{$this->db->quoteName($userRoleColumns['user_id'])}=:u_id " .
            "AND t1.{$this->db->quoteName($userRoleColumns['is_admin'])}=:is_admin",
            [
                'u_id' => $userId,
                'is_admin' => 1,
            ]
        );

        return count($userAdminRoles) > 0;
    }

    /**
     * @param string $name
     * @return string
     */
    public function quoteSingleName(string $name): string
    {
        return $this->db->quoteName($name);
    }

    /**
     * @return IStorage
     * @throws CryptException
     * @throws IDBException
     */
    private function getStorageInstance(): IStorage
    {
        switch ($this->getStorageType()) {
            case IAuth::STORAGE_DB:
                return new DBStorage(
                    $this->pdo,
                    $this->expire_time,
                    $this->suspend_time,
                    $this->namespace,
                    $this->config_parser,
                    $this->crypt_keys
                );
            case IAuth::STORAGE_COOKIE:
                return new CookieStorage(
                    $this->expire_time,
                    $this->suspend_time,
                    $this->namespace,
                    $this->config_parser,
                    $this->crypt_keys
                );
            case IAuth::STORAGE_SESSION:
            default:
                return new SessionStorage(
                    $this->expire_time,
                    $this->suspend_time,
                    $this->namespace,
                    $this->config_parser,
                    $this->crypt_keys
                );
        }
    }

    /**
     * @param int $resource_id
     * @param int $permission_id
     * @param int $user_id
     * @return bool
     * @throws IDBException
     */
    private function isAllow_(int $resource_id, int $permission_id, int $user_id): bool
    {
        try {
            // first check for resource permission for user
            $userResPermColumns = $this->config_parser->getTablesColumn($this->user_res_perm_key);
            $allowRec = $this->db->count(
                $this->tables[$this->user_res_perm_key],
                "{$this->db->quoteName($userResPermColumns['user_id'])}=:u_id " .
                "AND {$this->db->quoteName($userResPermColumns['resource_id'])}=:res_id " .
                "AND {$this->db->quoteName($userResPermColumns['perm_id'])}=:perm_id " .
                "AND {$this->db->quoteName($userResPermColumns['is_allow'])}=:allow",
                [
                    'u_id' => $user_id,
                    'res_id' => $resource_id,
                    'perm_id' => $permission_id,
                    'allow' => 1,
                ]);

            // if there is access for user
            if ($allowRec > 0) {
                return true;
            }

            // for checking access in roles of user
            $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
            $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
            $userRoles = $this->db->getFrom(
                $this->tables[$this->user_role_key],
                "{$this->db->quoteName($userRoleColumns['user_id'])}=:u",
                $userRoleColumns['id'],
                ['u' => $user_id]);

            // if user have role
            if (!count($userRoles)) return false;

            foreach ($userRoles as $key => $role) {
                if ($this->isAllowRole_($resource_id, $permission_id, $role[$roleColumns['id']])) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param int $storage_type
     * @return bool
     */
    private function isValidStorageType(int $storage_type): bool
    {
        return in_array($storage_type, IAuth::STORAGE_TYPES);
    }

    /**
     * @param null $username
     * @return int|null
     * @throws IDBException
     */
    private function getUserID_($username = null): ?int
    {
        $userId = null;
        if (is_null($username)) {
            $userId = $this->getCurrentUser()['id'] ?? null;
        } elseif (is_int($username)) {
            $userId = $username;
        } else {
            $userColumns = $this->config_parser->getTablesColumn($this->users_key);
            $user = $this->getFromUsers(
                $userColumns['id'],
                "{$this->db->quoteName($this->credential_columns['username'])}=:u",
                ['u' => $username]);
            if (count($user)) {
                $userId = $user[0][$userColumns['id']];
            }
        }

        return $userId;
    }

    /**
     * @param array|string $columns
     * @param string|null $where
     * @param array $bind_values
     * @return array
     * @throws IDBException
     */
    private function getFromUsers($columns = '*', $where = null, $bind_values = []): array
    {
        return $this->db->getFrom(
            $this->tables[$this->users_key],
            $where,
            $columns,
            $bind_values);
    }

    /**
     * @throws CryptException
     * @throws IDBException
     */
    private function reInstantiateStorage()
    {
        // create storage instance according to storage type
        $this->storage = $this->getStorageInstance();
        $this->storage->setExpireTime($this->expire_time);
        $this->storage->setSuspendTime($this->suspend_time);
    }
}