<?php

namespace Sim\Auth\Abstracts;

use PDO;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Helpers\DB;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Interfaces\IResource;
use Sim\Auth\Interfaces\IRole;

abstract class AbstractBaseAuth implements
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
     * @var ConfigParser
     */
    protected $config_parser;

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
     * @var array
     * Format:
     * [
     *   'username' => provided username column by user,
     *   'api_key' => provided api key column by user,
     * ]
     */
    protected $api_credential_columns = [];

    /********** table keys **********/

    /**
     * @var string
     */
    protected $api_keys_key = 'api_keys';

    /**
     * @var string
     */
    protected $api_key_role_key = 'api_key_role';

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
    protected $role_res_perm_key = 'role_res_perm';

    /**
     * AbstractBaseAuth constructor.
     * @param PDO $pdo_instance
     * @param array|null $config
     * @throws IDBException
     */
    public function __construct(PDO $pdo_instance, ?array $config = null)
    {
        $this->pdo = $pdo_instance;
        $this->db = new DB($pdo_instance);

        // load default config from _Config dir
        $this->default_config = include __DIR__ . '/../_Config/config.php';
        if (!\is_null($config)) {
            $this->setConfig($config);
        } else {
            $this->setConfig($this->default_config);
        }
    }

    /**
     * @param array $config
     * @param bool $merge_config
     * @return static
     * @throws IDBException
     * @throws ConfigException
     */
    public function setConfig(array $config, bool $merge_config = false)
    {
        if ($merge_config) {
            if (!empty($config)) {
                $this->config = \array_merge_recursive($this->default_config, $config);
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

        // get api credential columns
        $this->api_credential_columns = $this->config_parser->getAPICredentialColumns();

        return $this;
    }

    /**
     * @return static
     */
    public function runConfig()
    {
        $this->config_parser->up();
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
                if (\is_array($resourceArr)) {
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
            if (\is_null($resourceId)) continue;

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
        if (\is_null($resourceId)) return false;

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
                if (\is_array($roleArr)) {
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
            if (\is_null($roleId)) continue;

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
        if (\is_null($roleId)) return false;

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
    public function allowRole($resource, array $permission, $role)
    {
        // get resource id
        $resourceId = $this->getResourceID_($resource);
        if (\is_null($resourceId)) return $this;

        // get role id
        $roleId = $this->getRoleID_($role);
        if (\is_null($roleId)) return $this;

        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        foreach ($permission as $perm) {
            $this->db->updateIfExistsOrInsert(
                $this->tables[$this->role_res_perm_key],
                [
                    $this->db->quoteName($roleResPermColumns['role_id']) => $roleId,
                    $this->db->quoteName($roleResPermColumns['resource_id']) => $resourceId,
                    $this->db->quoteName($roleResPermColumns['perm_id']) => $perm,
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
        if (\is_null($resourceId)) return $this;

        // get role id
        $roleId = $this->getRoleID_($role);
        if (\is_null($roleId)) return $this;

        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        foreach ($permission as $perm) {
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
     * @param int $resource_id
     * @param int|array $permission_id
     * @param int $role_id
     * @return bool
     * @throws IDBException
     */
    protected function isAllowRole_(int $resource_id, $permission_id, int $role_id): bool
    {
        [$inArrStr, $inBindArr] = $this->createPermissionInStatement($permission_id);

        $roleResPermColumns = $this->config_parser->getTablesColumn($this->role_res_perm_key);
        $allowRole = $this->db->count(
            $this->tables[$this->role_res_perm_key],
            "{$this->db->quoteName($roleResPermColumns['role_id'])}=:r_id " .
            "AND {$this->db->quoteName($roleResPermColumns['resource_id'])}=:res_id " .
            "AND {$this->db->quoteName($roleResPermColumns['perm_id'])} IN {$inArrStr}",
            \array_merge([
                'r_id' => $role_id,
                'res_id' => $resource_id,
            ], $inBindArr));

        // if there is access for role
        if ($allowRole > 0) {
            return true;
        }

        return false;
    }

    /**
     * @param $permission_id
     * @return array
     */
    protected function createPermissionInStatement($permission_id): array
    {
        $recArr = [];
        $recBindArr = [];
        if (\is_array($permission_id)) {
            $counter = 1;
            foreach ($permission_id as $id) {
                $recArr[] = ":perm_id_" . $counter;
                $recBindArr['perm_id_' . $counter++] = $id;
            }
        } else {
            $recArr[] = ":perm_id_1";
            $recBindArr['perm_id_1'] = $permission_id;
        }
        $recArrStr = '(' . \implode(',', $recArr) . ')';

        return [$recArrStr, $recBindArr];
    }

    /**
     * @param $resource
     * @return int|null
     * @throws IDBException
     */
    protected function getResourceID_($resource): ?int
    {
        $resourceId = null;
        if (\is_int($resource)) {
            $resourceId = $resource;
        } else {
            $resourceColumns = $this->config_parser->getTablesColumn($this->resources_key);
            $rec = $this->getFromResources(
                $resourceColumns['id'],
                "{$this->db->quoteName($resourceColumns['name'])}=:r",
                ['r' => $resource]);

            if (\count($rec)) {
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
    protected function getRoleID_($role): ?int
    {
        $roleId = null;
        if (\is_int($role)) {
            $roleId = $role;
        } else {
            $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
            $role = $this->getFromRoles(
                $roleColumns['id'],
                "{$this->db->quoteName($roleColumns['name'])}=:r",
                ['r' => $role]);

            if (\count($role)) {
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
    protected function getFromResources($columns = '*', $where = null, $bind_values = []): array
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
    protected function getFromRoles($columns = '*', $where = null, $bind_values = []): array
    {
        return $this->db->getFrom(
            $this->tables[$this->roles_key],
            $where,
            $columns,
            $bind_values);
    }
}