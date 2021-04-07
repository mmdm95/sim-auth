<?php

namespace Sim\Auth\Interfaces;

interface IAPIAuthorizer
{
    /**
     * Check if current user's role is allow specific privilege to specific resource
     * For role-based applications
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param string|int $resource
     * @param int|array $permission
     * @param string|int|null $username
     * @return bool
     */
    public function isAllow($resource, $permission, $username = null): bool;

    /**
     * Allow access to specific permission to specific/current user
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param string|int $resource
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
     * @param string|int $resource
     * @param array $permission - array of int that specifies permission constants
     * @param null $username
     * @return static
     */
    public function disallowUser($resource, array $permission, $username = null);

    /**
     * Allow access to specific permission to specific/current_user role
     *
     * @param string|int $resource
     * @param array $permission - array of int that specifies permission constants
     * @param string|int $role
     * @return static
     */
    public function allowRole($resource, array $permission, $role);

    /**
     * Disallow access to specific permission to specific/current_user role
     *
     * @param string|int $resource
     * @param array $permission - array of int that specifies permission constants
     * @param string|int $role
     * @return static
     */
    public function disallowRole($resource, array $permission, $role);
}