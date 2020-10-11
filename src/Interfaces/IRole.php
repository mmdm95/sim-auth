<?php

namespace Sim\Auth\Interfaces;

interface IRole
{
    /**
     * Add role(s) for auth
     *
     * $roles has following structure:
     * [
     *   [
     *     column1 => value1,
     *     column2 => value2,
     *   ],
     *   [
     *     column1 => value3,
     *     column2 => value4,
     *   ],
     *   ...
     * ]
     *
     * @param array $roles
     * @return static
     */
    public function addRoles(array $roles);

    /**
     * Remove role(s) from auth
     *
     * Note:
     *   $roles should be array of roles' name or roles' id
     *
     * @param array $roles
     * @return static
     */
    public function removeRoles(array $roles);

    /**
     * Check that entered role is exists
     *
     * @param string $role
     * @return bool
     */
    public function hasRole(string $role): bool;

    /**
     * Get all roles
     *
     * Note:
     *   It contains all columns of roles table
     *
     * @return array
     */
    public function getRoles(): array;

    /**
     * Get all admin roles
     *
     * Note:
     *   It contains all columns of roles table
     *
     * @return array
     */
    public function getAdminRoles(): array;

    /**
     * Get all roles' name
     *
     * @return array
     */
    public function getRolesName(): array;

    /**
     * Get all admin roles' name
     *
     * @return array
     */
    public function getAdminRolesName(): array;

    /**
     * Get current/loggedIn user's role
     *
     * @param string|int $username
     * @return array
     */
    public function getUserRole($username): array;

    /**
     * Get current/loggedIn user's role
     *
     * @return array
     */
    public function getCurrentUserRole(): array;

    /**
     * Add role(s) for auth
     *
     * Note:
     *   You should pass an array of roles' name
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param array $roles
     * @param null $username
     * @return static
     */
    public function addRoleToUser(array $roles, $username = null);

    /**
     * Check if $username is admin
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param string|int|null $username
     * @return bool
     */
    public function isAdmin($username = null): bool;
}