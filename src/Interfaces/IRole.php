<?php

namespace Sim\Auth\Interfaces;

interface IRole
{
    /**
     * Add role(s) for auth
     *
     * @param array $roles
     * @return static
     */
    public function addRoles(array $roles);

    /**
     * Remove role(s) from auth
     *
     * @param array $roles
     * @return static
     */
    public function removeRoles(array $roles);

    /**
     * Check that entered role is exists
     *
     * @param string $role
     * @param bool $check_in_db
     * @return bool
     */
    public function hasRole(string $role, bool $check_in_db = false): bool;

    /**
     * Get all roles
     *
     * @param bool $check_in_db
     * @return array
     */
    public function getRoles(bool $check_in_db = false): array;

    /**
     * Get current/loggedIn user's role
     *
     * @return array
     */
    public function getCurrentUserRole(): array;

    /**
     * Check if $role is in admin roles
     * If $role is not set, then check current user
     *
     * @param int|string|null $role
     * @param bool $check_in_db
     * @return bool
     */
    public function isAdmin($role = null, bool $check_in_db = false): bool;
}