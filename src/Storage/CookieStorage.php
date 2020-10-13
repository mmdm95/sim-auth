<?php

namespace Sim\Auth\Storage;

use Sim\Auth\Abstracts\AbstractStorage;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IDBException;
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
    }

    /**
     * {@inheritdoc}
     * @throws CookieException
     * @throws IDBException
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
            null,
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
        $cookieVal = json_decode($cookieVal, true);
        if (false === $cookieVal || empty($cookieVal)) {
            $cookieVal = null;
        }
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
     * @throws IDBException
     */
    public function updateSuspendTime()
    {
        if(!$this->hasExpired() && $this->evaluateStorageValue()) {
            $this->cookie->remove($this->sus_key);
            // suspend cookie
            $setCookie = new SetCookie(
                $this->sus_key,
                'suspend_val',
                time() + $this->suspend_time,
                '/',
                null,
                null,
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
        $expireVal = $this->restore();
        $res = is_null($expireVal);

        if (IAuth::STATUS_ACTIVE === $this->getStatus() && $res) {
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

        if (IAuth::STATUS_ACTIVE === $this->getStatus() && $res) {
            $this->setStatus(IAuth::STATUS_SUSPEND);
        }

        return $res;
    }

    /**
     * @return bool
     * @throws IDBException
     */
    protected function evaluateStorageValue(): bool
    {
        $restoredVal = $this->restore();
        if (empty($restoredVal)) return false;

        $userColumns = $this->config_parser->getTablesColumn($this->users_key);

        $where = "{$userColumns['username']}=:u";
        $bindValues = [
            'u' => $restoredVal['username'],
        ];

        $user = $this->db->getFrom(
            $this->tables[$this->users_key],
            $where,
            $userColumns['password'],
            $bindValues
        );

        if (count($user) !== 1) return false;

        $password = $user[0][$userColumns['password']];

        // if we do not have any password to verify,
        // then we do not have any verifier
        if (is_null($this->verifier)) return true;

        // verify password with user's password in db
        $verified = $this->verifier->verify($this->$restoredVal['password'], $password);

        if ($verified) return true;

        return false;
    }
}