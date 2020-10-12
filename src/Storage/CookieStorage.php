<?php

namespace Sim\Auth\Storage;

use Sim\Auth\Abstracts\AbstractStorage;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Interfaces\IAuth;
use Sim\Cookie\Cookie;
use Sim\Cookie\Exceptions\CookieException;
use Sim\Cookie\Interfaces\ICookie;
use Sim\Cookie\SetCookie;
use Sim\Crypt\Exceptions\CryptException;

class CookieStorage extends AbstractStorage
{
    /**
     * @var ICookie
     */
    protected $cookie;

    /**
     * @var string
     */
    protected $storage_name = '__Sim_Auth_Cookie__';

    /**
     * CookieStorage constructor.
     * @param int $expire_time
     * @param int $suspend_time
     * @param string $namespace
     * @param ConfigParser $config_parser
     * @param array $crypt_keys
     * @throws CryptException
     */
    public function __construct(
        int $expire_time,
        int $suspend_time,
        string $namespace,
        ConfigParser $config_parser,
        array $crypt_keys = []
    )
    {
        parent::__construct(
            $expire_time,
            $suspend_time,
            $namespace,
            $config_parser,
            $crypt_keys
        );
        $this->cookie = new Cookie($this->crypt);
        $this->exp_key = $this->storage_name . '-' . $this->namespace . '-credentials';
        $this->sus_key = $this->storage_name . '-' . $this->namespace . '-suspend_time';
    }

    /**
     * {@inheritdoc}
     * @throws CookieException
     */
    public function store(array $credentials)
    {
        $this->updateSuspendTime();
        // expire cookie
        $setCookie = new SetCookie(
            $this->exp_key,
            json_encode($credentials),
            time() + $this->expire_time,
            '/',
            null,
            true,
            true
        );
        $this->cookie->set($setCookie);
        $this->setStatus(IAuth::STATUS_ACTIVE);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restore(): ?array
    {
        $cookieVal = $this->cookie->get($this->exp_key, null);
        return $cookieVal;
    }

    /**
     * {@inheritdoc}
     * @throws CookieException
     */
    public function delete()
    {
        $this->cookie->remove($this->sus_key);
        $this->cookie->remove($this->exp_key);
        $this->setStatus(IAuth::STATUS_NONE);
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws CookieException
     */
    public function updateSuspendTime()
    {
        if(!$this->hasExpired()) {
            $this->cookie->remove($this->sus_key);
            // suspend cookie
            $setCookie = new SetCookie(
                $this->sus_key,
                'suspend_val',
                time() + $this->suspend_time,
                '/',
                null,
                true,
                true
            );
            $this->cookie->set($setCookie);
            $this->setStatus(IAuth::STATUS_ACTIVE);
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function hasExpired(): bool
    {
        $expireVal = $this->cookie->get($this->exp_key, null);
        $res = is_null($expireVal);
        if ($res) {
            $this->setStatus(IAuth::STATUS_EXPIRE);
        }
        return $res;
    }

    /**
     * {@inheritdoc}
     */
    public function hasSuspended(): bool
    {
        $suspendVal = $this->cookie->get($this->sus_key, null);
        $res = is_null($suspendVal);
        if (!$this->hasExpired() && $this->status === IAuth::STATUS_ACTIVE && $res) {
            $this->setStatus(IAuth::STATUS_SUSPEND);
        } else {
            $this->setStatus(IAuth::STATUS_NONE);
        }
        return $res;
    }
}