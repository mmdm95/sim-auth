<?php

namespace Sim\Auth\Abstracts;

use InvalidArgumentException;
use PDO;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\DB;
use Sim\Auth\Exceptions\InvalidStorageTypeException;
use Sim\Auth\Exceptions\InvalidUserException;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IAuthenticator;
use Sim\Auth\Interfaces\IAuthorizer;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Interfaces\IResource;
use Sim\Auth\Interfaces\IRole;
use Sim\Auth\Interfaces\IStorage;
use Sim\Auth\Storage\CookieStorage;
use Sim\Auth\Storage\DBStorage;
use Sim\Auth\Storage\SessionStorage;
use Sim\Auth\Utils\AuthUtil;
use Sim\Cookie\Exceptions\CookieException;
use Sim\Crypt\Exceptions\CryptException;

abstract class AbstractAuth implements
    IAuthenticator,
    IAuthorizer,
    IResource,
    IRole
{
    /**
     * @var PDO $pdo
     */
    protected $pdo;

    /**
     * @var DB
     */
    protected $db;

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
     * @var array $default_config
     */
    protected $default_config = [];

    /**
     * @var array $config
     */
    protected $config = [];

    /**
     * @var array $tables
     */
    protected $tables = [];

    /**
     * @var array
     * Format:
     * [
     *   'username' => provided username column by user,
     *   'password' => provided password column by user,
     * ]
     */
    protected $credential_columns = [];

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
     * @var ConfigParser
     */
    protected $config_parser;

    /**
     * @var bool $merge_config
     */
    protected $merge_config = false;

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
    protected $roles_key = 'roles';

    /**
     * @var string
     */
    protected $resources_key = 'resources';

    /**
     * @var string
     */
    protected $user_role_key = 'user_role';

    /**
     * @var string
     */
    protected $role_res_perm_key = 'role_res_perm';

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
        $this->pdo = $pdo_instance;
        $this->db = new DB($this->pdo);

        if (empty($credentials)) {
            throw new InvalidArgumentException('Please fill credentials array.');
        }

        $this->crypt_keys = $crypt_keys;

        // load default config from _Config dir
        $this->default_config = include __DIR__ . '/../_Config/config.php';
        if (!is_null($config)) {
            $this->setConfig($config);
        } else {
            $this->setConfig($this->default_config);
        }

        if (!$this->isValidStorageType($storage_type)) {
            throw new InvalidStorageTypeException(
                'Storage must be one of [db] or [cookie] or [session]. Use [IAuth] constants please'
            );
        } else {
            $this->setStorageType($storage_type);
        }

        // is must be at the end because storage instance
        // will create after setting namespace
        $this->setNamespace($namespace);
    }

    /**
     * @param bool $answer
     * @return static
     */
    public function mergeConfig(bool $answer)
    {
        $this->merge_config = $answer;
        return $this;
    }

    /**
     * @param array $config
     * @return static
     * @throws IDBException
     */
    public function setConfig(array $config)
    {
        if ($this->merge_config) {
            if (!empty($config)) {
                $this->config = array_merge_recursive($this->default_config, $config);
            }
        } else {
            $this->config = $config;
        }

        // parse config
        $this->config_parser = new ConfigParser($this->config, $this->pdo);

        // get tables
        $this->tables = $this->config_parser->getTables();

        // get credential columns
        $this->credential_columns = $this->config_parser->getCredentialColumns();

        return $this;
    }

    /**
     * @return static
     * @throws IDBException
     */
    public function runConfig()
    {
        $this->config_parser->up();
        return $this;
    }

    /**
     * @return int
     */
    public function getStatus(): int
    {
        return $this->storage->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired(): bool
    {
        return IAUTH::STATUS_EXPIRE === $this->getStatus();
    }

    /**
     * {@inheritdoc}
     */
    public function isSuspended(): bool
    {
        return IAUTH::STATUS_SUSPEND === $this->getStatus();
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
     */
    public function setStorageType(int $type)
    {
        if ($this->isValidStorageType($type)) {
            $this->storage_type = $type;
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

            // create storage instance according to storage type
            $this->storage = $this->getStorageInstance();
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

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function logout()
    {
        $this->storage->delete();
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
     * @throws CookieException
     * @throws IDBException
     */
    public function destroySession(string $session_uuid)
    {
        if ($this->getStorageType() === IAuth::STORAGE_DB) {
            /**
             * @var DBStorage $storage
             */
            $storage = $this->storage;
            $storage->destroy($session_uuid);
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCurrentUser()
    {
        $currentUser = $this->storage->restore();
        if (is_null($currentUser)) return null;
        return $currentUser['id'];
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
    public function allowRole($resource, array $permission, $role)
    {
        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (is_null($resourceId)) return $this;

        // get role id
        $roleId = $this->getRoleID_($role);
        if (is_null($roleId)) return $this;

        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        foreach ($permission as $perm) {
            if (!$this->isValidPermission($perm)) continue;

            $this->db->updateIfExistsOrInsert(
                $this->tables[$this->role_res_perm_key],
                [
                    $roleResPermColumns['role_id'] => $roleId,
                    $roleResPermColumns['resource_id'] => $resourceId,
                    $roleResPermColumns['perm_id'] => $perm,
                ],
                "{$this->db->quoteName($roleResPermColumns['role_id'])}=:r_id " .
                "AND {$this->db->quoteName($roleResPermColumns['resource_id'])}=:rec_id " .
                "AND {$this->db->quoteName($roleResPermColumns['perm_id'])}=:perm_id",
                [
                    'r_id' => $roleId,
                    'rec_id' => $resourceId,
                    'perm_id' => $perm,
                ]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function disallowRole($resource, array $permission, $role)
    {
        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (is_null($resourceId)) return $this;

        // get role id
        $roleId = $this->getRoleID_($role);
        if (is_null($roleId)) return $this;

        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        foreach ($permission as $perm) {
            if (!$this->isValidPermission($perm)) continue;

            $this->db->delete(
                $this->tables[$this->role_res_perm_key],
                "{$this->db->quoteName($roleResPermColumns['role_id'])}=:r_id " .
                "AND {$this->db->quoteName($roleResPermColumns['resource_id'])}=:rec_id " .
                "AND {$this->db->quoteName($roleResPermColumns['perm_id'])}=:perm_id",
                [
                    'r_id' => $roleId,
                    'rec_id' => $resourceId,
                    'perm_id' => $perm,
                ]);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function addResources(array $resources)
    {
        try {
            foreach ($resources as $resourceArr) {
                if (is_array($resourceArr)) {
                    $addArr = [];

                    foreach ($resourceArr as $column => $value) {
                        $addArr[$column] = $value;
                    }

                    if (!empty($addArr)) {
                        $this->db->insert($this->tables[$this->resources_key], $addArr);
                    }
                }
            }
        } catch (\Exception $e) {
            // do nothing for now
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function removeResources(array $resources)
    {
        $resourceColumns = $this->config_parser->getTablesColumn($this->resources_key);
        foreach ($resources as $resource) {
            // get resource id
            $resourceId = $this->getResourceID_($resource);
            if (is_null($resourceId)) continue;

            $this->db->delete(
                $this->tables[$this->resources_key],
                "{$this->db->quoteName($resourceColumns['id'])}=:rec_id",
                [
                    'rec_id' => $resourceId,
                ]
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function hasResource($resource): bool
    {
        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (is_null($resourceId)) return false;

        $resourceColumns = $this->config_parser->getTablesColumn($this->resources_key);
        $count = $this->db->count(
            $this->tables[$this->resources_key],
            "{$this->db->quoteName($resourceColumns['id'])}=:rec_id",
            [
                'rec_id' => $resourceId,
            ]
        );

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getResources(): array
    {
        return $this->getFromResources();
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getResourcesNames(): array
    {
        $resourceColumns = $this->config_parser->getTablesColumn($this->resources_key);
        return $this->getFromResources($resourceColumns['name']);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function addRoles(array $roles)
    {
        try {
            foreach ($roles as $roleArr) {
                if (is_array($roleArr)) {
                    $addArr = [];

                    foreach ($roleArr as $column => $value) {
                        $addArr[$column] = $value;
                    }

                    if (!empty($addArr)) {
                        $this->db->insert($this->tables[$this->roles_key], $addArr);
                    }
                }
            }
        } catch (\Exception $e) {
            // do nothing for now
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function removeRoles(array $roles)
    {
        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        foreach ($roles as $role) {
            // get resource id
            $roleId = $this->getRoleID_($role);
            if (is_null($roleId)) continue;

            $this->db->delete(
                $this->tables[$this->roles_key],
                "{$this->db->quoteName($roleColumns['id'])}=:r_id",
                [
                    'r_id' => $roleId,
                ]
            );
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @throws IDBException
     */
    public function hasRole(string $role): bool
    {
        // get role id
        $roleId = $this->getRoleID_($role);
        if (is_null($roleId)) return false;

        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        $count = $this->db->count(
            $this->tables[$this->roles_key],
            "{$this->db->quoteName($roleColumns['id'])}=:r_id",
            [
                'r_id' => $roleId,
            ]
        );

        return $count > 0;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getRoles(): array
    {
        return $this->getFromRoles();

    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getAdminRoles(): array
    {
        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        return $this->getFromRoles(
            '*',
            "{$this->db->quoteName($roleColumns['is_admin'])}=:is_admin",
            [
                'is_admin' => 1,
            ]
        );
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getRolesName(): array
    {
        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        return $this->getFromRoles($roleColumns['name']);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function getAdminRolesName(): array
    {
        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        return $this->getFromRoles(
            $roleColumns['name'],
            "{$this->db->quoteName($roleColumns['is_admin'])}=:is_admin",
            [
                'is_admin' => 1,
            ]
        );
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
    public function addRoleToUser(array $role, $username = null)
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return $this;

        $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
        foreach ($role as $r) {
            // get role id
            $roleId = $this->getRoleID_($r);
            if (is_null($roleId)) continue;

            $this->db->insert(
                $this->tables[$this->user_role_key],
                [
                    $userRoleColumns['user_id'] => $userId,
                    $userRoleColumns['role_id'] => $roleId,
                ]
            );
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

        return (bool)count($userAdminRoles);
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
            $userRoleColumns = $this->config_parser->getTablesColumn($this->user_role_key);
            $userRoles = $this->db->getFrom(
                $this->tables[$this->user_role_key],
                "{$this->db->quoteName($userRoleColumns['user_id'])}=:u",
                $userRoleColumns['id'],
                ['u' => $user_id]);

            // if user have role
            if (!count($userRoles)) return false;

            foreach ($userRoles as $key => $role) {
                if ($this->isAllowRole_($resource_id, $permission_id, $role['id'])) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param int $resource_id
     * @param int $permission_id
     * @param int $role_id
     * @return bool
     * @throws IDBException
     */
    private function isAllowRole_(int $resource_id, int $permission_id, int $role_id): bool
    {
        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        $allowRole = $this->db->count(
            $this->tables[$this->roles_key],
            "{$this->db->quoteName($roleResPermColumns['role_id'])}=:r_id " .
            "AND {$this->db->quoteName($roleResPermColumns['resource_id'])}=:res_id " .
            "AND {$this->db->quoteName($roleResPermColumns['perm_id'])}=:perm_id " .
            [
                'r_id' => $role_id,
                'res_id' => $resource_id,
                'perm_id' => $permission_id,
            ]);

        // if there is access for role
        if ($allowRole > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param int $permission
     * @return bool
     */
    private function isValidPermission(int $permission): bool
    {
        return in_array($permission, IAuth::PERMISSIONS);
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
            $userId = $this->getCurrentUser();
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
     * @param $resource
     * @return int|null
     * @throws IDBException
     */
    private function getResourceID_($resource): ?int
    {
        $resourceId = null;
        if (is_int($resource)) {
            $resourceId = $resource;
        } else {
            $resourceColumns = $this->config_parser->getTablesColumn($this->resources_key);
            $rec = $this->getFromResources(
                $resourceColumns['id'],
                "{$resourceColumns['name']}=:r",
                ['r' => $resource]);

            if (count($rec)) {
                $resourceId = $rec[0][$resourceColumns['id']];
            }
        }

        return $resourceId;
    }

    /**
     * @param $role
     * @return int|null
     * @throws IDBException
     */
    private function getRoleID_($role): ?int
    {
        $roleId = null;
        if (is_int($role)) {
            $roleId = $role;
        } else {
            $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
            $role = $this->getFromResources(
                $roleColumns['id'],
                "{$roleColumns['name']}=:r",
                ['r' => $role]);

            if (count($role)) {
                $roleId = $role[0][$roleColumns['id']];
            } else {
                return null;
            }
        }

        return $roleId;
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
     * @param array|string $columns
     * @param string|null $where
     * @param array $bind_values
     * @return array
     * @throws IDBException
     */
    private function getFromResources($columns = '*', $where = null, $bind_values = []): array
    {
        return $this->db->getFrom(
            $this->tables[$this->resources_key],
            $where,
            $columns,
            $bind_values);
    }

    /**
     * @param array|string $columns
     * @param string|null $where
     * @param array $bind_values
     * @return array
     * @throws IDBException
     */
    private function getFromRoles($columns = '*', $where = null, $bind_values = []): array
    {
        return $this->db->getFrom(
            $this->tables[$this->roles_key],
            $where,
            $columns,
            $bind_values);
    }
}