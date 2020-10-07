<?php

namespace Sim\Auth\Interfaces;

interface IAuth
{
    const STATUS_NONE = 1;
    const STATUS_ACTIVE = 2;
    const STATUS_EXPIRE = 3;
    const STATUS_SUSPEND = 4;

    const STORAGE_DB = 1;
    const STORAGE_COOKIE = 2;
    const STORAGE_SESSION = 3;

    const STORAGE_TYPES = [
        self::STORAGE_DB,
        self::STORAGE_COOKIE,
        self::STORAGE_SESSION,
    ];

    const PERMISSION_CREATE = 1;
    const PERMISSION_READ = 2;
    const PERMISSION_UPDATE = 3;
    const PERMISSION_DELETE = 4;
}