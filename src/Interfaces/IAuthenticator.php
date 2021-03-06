<?php

namespace Sim\Auth\Interfaces;

interface IAuthenticator
{
    /**
     * Do login with specified credentials
     *
     * It should be as format below:
     * [
     *   'username' => provided username,
     *   'password' => provided password,
     * ]
     *
     * @param array $credentials
     * @param string|null $extra_query
     * @param array $bind_values
     * @return static
     */
    public function login(array $credentials, string $extra_query = null, array $bind_values = []);

    /**
     * @param int $id
     * @return static
     */
    public function loginWithID(int $id);

    /**
     * Resume user's login from stored credential(s)
     *
     * @return static
     */
    public function resume();

    /**
     * Do logout
     *
     * @return static
     */
    public function logout();

    /**
     * Check if current user is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool;

    /**
     * Check if current user's login expired time ended
     *
     * @return bool
     */
    public function isExpired(): bool;

    /**
     * Check if current user's suspend time ended
     *
     * @return bool
     */
    public function isSuspended(): bool;

    /**
     * Check if current status is none
     *
     * @return bool
     */
    public function isNone(): bool;

    /**
     * Reassign/Extend suspend time
     *
     * @return static
     */
    public function extendSuspendTime();

    /**
     * Set expiration time for user's login
     *
     * @param string|int $timestamp
     * @return static
     */
    public function setExpiration($timestamp);

    /**
     * Get expiration time for user's login
     *
     * @return int
     */
    public function getExpiration(): int;

    /**
     * Set suspend time for user's login
     *
     * @param string|int $timestamp
     * @return static
     */
    public function setSuspendTime($timestamp);

    /**
     * Get suspend time for user's login
     *
     * @return int
     */
    public function getSuspendTime(): int;

    /**
     * Set storage type to store identities
     *
     * @param int $type
     * @return static
     */
    public function setStorageType(int $type);

    /**
     * Get storage type
     *
     * @return int
     */
    public function getStorageType(): int;

    /**
     * Set passed namespace
     * Useful for multiple authentication
     *
     * @param string $namespace
     * @return static
     */
    public function setNamespace(string $namespace);

    /**
     * Get current namespace
     * Useful for multiple authentication
     *
     * @return string
     */
    public function getNamespace(): string;
}