<?php

namespace Sim\Auth\Interfaces;

interface IAuthorizer extends IAPIAuthorizer
{
    /**
     * Use only with [STORAGE_DB] otherwise it'll not work
     *
     * Note:
     *   If [$username] parameter is null this means we point to current user
     *
     * @param string|int|null $username
     * @return array
     */
    public function getSessionUUID($username = null): array;

    /**
     * Use only with [STORAGE_DB] otherwise it'll not work
     *
     * @param string $session_uuid
     * @return bool
     */
    public function destroySession(string $session_uuid): bool;
}