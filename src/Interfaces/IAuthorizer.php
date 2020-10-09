<?php

namespace Sim\Auth\Interfaces;

interface IAuthorizer
{
    /**
     * Use only with [STORAGE_DB] otherwise it'll not work
     *
     * @param string $session_uuid
     * @return static
     */
    public function destroySession(string $session_uuid);

    /**
     * Check if current user's role is allow specific privilege to specific resource
     * For role-based applications
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param string|int $resource
     * @param int $permission
     * @param string|int|null $username
     * @return bool
     */
    public function isAllow($resource, int $permission, $username = null): bool;

    /**
     * Allow access to specific permission to specific/current user
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param $resource
     * @param array $permission - array of int that specifies permission constants
     * @param null $username
     * @return static
     */
    public function allowUser($resource, array $permission, $username = null);

    /**
     * Disallow access to specific permission to specific/current user
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param $resource
     * @param array $permission - array of int that specifies permission constants
     * @param null $username
     * @return static
     */
    public function disallowUser($resource, array $permission, $username = null);

    /**
     * Allow access to specific permission to specific/current_user role
     *
     * Note:
     *   If [$role] parameter is null this means we point to current user' roles
     *
     * @param $resource
     * @param array $permission - array of int that specifies permission constants
     * @param string|int|null $role
     * @return static
     */
    public function allowRole($resource, array $permission, $role = null);

    /**
     * Disallow access to specific permission to specific/current_user role
     *
     * Note:
     *   If [$role] parameter is null this means we point to current user' roles
     *
     * @param $resource
     * @param array $permission - array of int that specifies permission constants
     * @param string|int|null $role
     * @return static
     */
    public function disallowRole($resource, array $permission, $role = null);
}