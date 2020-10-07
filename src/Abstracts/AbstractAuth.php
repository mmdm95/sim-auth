<?php

namespace Sim\Auth\Abstracts;

use InvalidArgumentException;
use Sim\Auth\Exceptions\InvalidStorageTypeException;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IAuthenticator;
use Sim\Auth\Interfaces\IAuthException;
use Sim\Auth\Interfaces\IAuthorizer;
use Sim\Auth\Interfaces\IPage;
use Sim\Auth\Interfaces\IRole;

abstract class AbstractAuth implements
    IAuthenticator,
    IAuthorizer,
    IPage,
    IRole
{
    /**
     * @var array $credentials
     */
    protected $credentials = [];

    /**
     * @var array $default_config
     */
    protected $default_config = [];

    /**
     * @var array $config
     */
    protected $config = [];

    /**
     * @var int $expire_time
     */
    protected $expire_time = 31536000 /* 1year */
    ;

    /**
     * @var int $suspend_time
     */
    protected $suspend_time = 1800 /* 30min */
    ;

    /**
     * @var int $storage_type
     */
    protected $storage_type = IAuth::STORAGE_DB;

    /**
     * @var string $namespace
     */
    protected $namespace = 'default';

    /**
     * @var bool $merge_config
     */
    protected $merge_config = false;

    /**
     * @var int $status
     */
    protected $status = IAUTH::STATUS_NONE;

    /**
     * AbstractAuth constructor.
     * @param array $credentials
     * @param array|null $config
     * @param int $storage_type
     * @throws IAuthException
     */
    public function __construct(
        array $credentials,
        ?array $config = null,
        int $storage_type = IAuth::STORAGE_COOKIE
    )
    {
        if (empty($credentials)) {
            throw new InvalidArgumentException('Please fill credentials array.');
        }

        $this->credentials = $credentials;

        // load default config from _Config dir
        $this->default_config = include __DIR__ . '../_Config/config.php';
        if (!is_null($config)) {
            $this->setConfig($config);
        }

        if ($this->isValidStorageType($storage_type)) {
            throw new InvalidStorageTypeException(
                'Storage must be one of [cookie] or [session]. Use [IAuth] constants please'
            );
        } else {
            $this->setStorageType($storage_type);
        }

        // take config actions and etc.
        $this->init();
    }

    /**
     * @param bool $answer
     * @return static
     */
    public function mergeConfig(bool $answer)
    {
        $this->merge_config = $answer;
        return $this;
    }

    /**
     * @param array $config
     * @return static
     */
    public function setConfig(array $config)
    {
        if ($this->merge_config) {
            if (!empty($config)) {
                $this->config = array_merge_recursive($this->default_config, $config);
            }
        } else {
            $this->config = $config;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isLoggedIn(): bool
    {
        return IAUTH::STATUS_ACTIVE == $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function isExpired(): bool
    {
        return IAUTH::STATUS_EXPIRE == $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function isSuspended(): bool
    {
        return IAUTH::STATUS_SUSPEND == $this->status;
    }

    /**
     * {@inheritdoc}
     */
    public function setExpiration(int $timestamp)
    {
        if ($this->isValidTimestamp($timestamp)) {
            $this->expire_time = $timestamp;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getExpiration(): int
    {
        return $this->expire_time;
    }

    /**
     * {@inheritdoc}
     */
    public function setSuspendTime(int $timestamp)
    {
        if ($this->isValidTimestamp($timestamp)) {
            $this->suspend_time = $timestamp;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getSuspendTime(): int
    {
        return $this->suspend_time;
    }

    /**
     * {@inheritdoc}
     */
    public function setStorageType(int $type)
    {
        if ($this->isValidStorageType($type)) {
            $this->storage_type = $type;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStorageType(): int
    {
        return $this->storage_type;
    }

    /**
     * {@inheritdoc}
     */
    public function setNamespace(string $namespace)
    {
        if (!empty($namespace)) {
            $this->namespace = $namespace;
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }


    /**
     * {@inheritdoc}
     */
    public function isAllow($resource, int $permission, $username = null): bool
    {

    }

    /**
     * {@inheritdoc}
     */
    public function allowUser($resource, array $permission, $username = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disallowUser($resource, array $permission, $username = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function allowRole($resource, array $permission, $role = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function disallowRole($resource, array $permission, $role = null)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function addPages(array $pages)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removePages(array $pages)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasPage(string $page): bool
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getPages(bool $check_in_db = false): array
    {

    }

    /**
     * {@inheritdoc}
     */
    public function addRoles(array $roles)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function removeRoles(array $roles)
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     */
    public function hasRole(string $role, bool $check_in_db = false): bool
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getRoles(bool $check_in_db = false): array
    {

    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentUserRole(): array
    {

    }

    /**
     * {@inheritdoc}
     */
    public function isAdmin($role = null, bool $check_in_db = false): bool
    {

    }

    /**
     * Do initialize needed functionality
     */
    protected function init()
    {

    }

    /**
     * @param int $storage_type
     * @return bool
     */
    private function isValidStorageType(int $storage_type): bool
    {
        return in_array($storage_type, IAuth::STORAGE_TYPES);
    }

    /**
     * @param $timestamp
     * @return bool
     */
    private function isValidTimestamp($timestamp): bool
    {
        return ($timestamp <= PHP_INT_MAX)
            && ($timestamp >= ~PHP_INT_MAX);
    }
}