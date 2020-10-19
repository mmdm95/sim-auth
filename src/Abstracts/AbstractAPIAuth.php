<?php

namespace Sim\Auth\Abstracts;

use Sim\Auth\Interfaces\IDBException;

abstract class AbstractAPIAuth extends AbstractBaseAuth
{
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
                        $userRoleColumns['api_key_id'] => $userId,
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
     * @param null $username
     * @return int|null
     * @throws IDBException
     */
    private function getUserID_($username = null): ?int
    {
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