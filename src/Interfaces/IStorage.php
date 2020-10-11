<?php

namespace Sim\Auth\Interfaces;

interface IStorage
{
    /**
     * Store credentials to storage
     *
     * $credentials has following format:
     * [
     *   'username' => provided username by user,
     *   'password' => provided password by user,
     * ]
     *
     * @param array $credentials
     * @return static
     */
    public function store(array $credentials);

    /**
     * Restore credentials to storage
     *
     * @return array|null
     */
    public function restore(): ?array;

    /**
     * Delete stored credentials from storage
     *
     * @return static
     */
    public function delete();

    /**
     * @param int $status
     * @return static
     */
    public function setStatus(int $status);

    /**
     * @return int
     */
    public function getStatus(): int;

    /**
     * @return static
     */
    public function updateSuspendTime();

    /**
     * @return bool
     */
    public function hasExpired(): bool;

    /**
     * @return bool
     */
    public function hasSuspended(): bool;
}