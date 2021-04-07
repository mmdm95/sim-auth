<?php

namespace Sim\Auth\Abstracts;

use Sim\Auth\Interfaces\IDBException;

abstract class AbstractAPIAuth extends AbstractBaseAuth
{
    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function isAllow($resource, int $permission, $username = null): bool
    {
        try {
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
    public function getUserRole($username): array
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return [];

        $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
        $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);
        return $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->roles_key] . ' AS t1',
            $this->tables[$this->api_key_role_key] . ' AS t2',
            "t1.{$this->db->quoteName($roleColumns['id'])}=t2.{$this->db->quoteName($apiKeyRoleColumns['role_id'])}",
            "t2.{$this->db->quoteName($apiKeyRoleColumns['api_key_id'])}=:u_id",
            '*',
            [
                'u_id' => $userId,
            ]
        );
    }

    /**
     * There is no roles to show in API for current user
     *
     * {@inheritdoc}
     */
    public function getCurrentUserRole(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function userHasRole($role, $username = null): bool
    {
        // get user id
        $userId = $this->getUserID_($username);
        if (is_null($userId)) return false;
        // get role id
        $roleId = $this->getRoleID_($role);
        if (is_null($roleId)) false;

        $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);
        $userRole = $this->db->count(
            $this->tables[$this->api_key_role_key],
            "{$apiKeyRoleColumns['api_key_id']}=:u_id AND {$apiKeyRoleColumns['role_id']}=:r_id",
            [
                'u_id' => $userId,
                'r_id' => $roleId,
            ]
        );

        return 0 !== $userRole;
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

        $userRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);
        foreach ($roles as $r) {
            // get role id
            $roleId = $this->getRoleID_($r);
            if (is_null($roleId)) continue;

            // get user's role count
            $roleCount = $this->db->count(
                $this->tables[$this->api_key_role_key],
                "{$userRoleColumns['api_key_id']}=:u_id AND {$userRoleColumns['role_id']}=:r_id",
                [
                    'u_id' => $userId,
                    'r_id' => $roleId,
                ]
            );
            // add it to database if we have not that role there
            if (0 === $roleCount) {
                $this->db->insert(
                    $this->tables[$this->api_key_role_key],
                    [
                        $this->db->quoteName($userRoleColumns['api_key_id']) => $userId,
                        $this->db->quoteName($userRoleColumns['role_id']) => $roleId,
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
        $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);
        $userAdminRoles = $this->db->getFromJoin(
            'INNER',
            $this->tables[$this->roles_key] . ' AS t1',
            $this->tables[$this->api_key_role_key] . ' AS t2',
            "t1.{$this->db->quoteName($roleColumns['id'])}=t2.{$this->db->quoteName($apiKeyRoleColumns['role_id'])}",
            "t2.{$this->db->quoteName($apiKeyRoleColumns['api_key_id'])}=:u_id " .
            "AND t1.{$this->db->quoteName($apiKeyRoleColumns['is_admin'])}=:is_admin",
            [
                'u_id' => $userId,
                'is_admin' => 1,
            ]
        );

        return count($userAdminRoles) > 0;
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
            // for checking access in roles of user
            $roleColumns = $this->config_parser->getTablesColumn($this->roles_key);
            $apiKeyRoleColumns = $this->config_parser->getTablesColumn($this->api_key_role_key);
            $userRoles = $this->db->getFrom(
                $this->tables[$this->api_key_role_key],
                "{$this->db->quoteName($apiKeyRoleColumns['api_key_id'])}=:u",
                $apiKeyRoleColumns['id'],
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
     * @param null $username
     * @return int|null
     * @throws IDBException
     */
    private function getUserID_($username = null): ?int
    {
        if (is_null($username)) return null;

        $userId = null;
        if (is_int($username)) {
            $userId = $username;
        } else {
            $apiKeyColumns = $this->config_parser->getTablesColumn($this->api_keys_key);
            $user = $this->getFromUsers(
                $apiKeyColumns['id'],
                "{$this->db->quoteName($this->api_credential_columns['username'])}=:u",
                ['u' => $username]);
            if (count($user)) {
                $userId = $user[0][$apiKeyColumns['id']];
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
            $this->tables[$this->api_keys_key],
            $where,
            $columns,
            $bind_values);
    }
}