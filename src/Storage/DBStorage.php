<?php

namespace Sim\Auth\Storage;

use Jenssegers\Agent\Agent;
use PDO;
use Sim\Auth\Abstracts\AbstractStorage;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Helpers\DB;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Utils\AuthUtil;
use Sim\Auth\Utils\UUIDUtil;
use Sim\Cookie\Cookie;
use Sim\Cookie\Exceptions\CookieException;
use Sim\Cookie\Interfaces\ICookie;
use Sim\Cookie\SetCookie;

class DBStorage extends AbstractStorage
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var ICookie
     */
    protected $cookie;

    /********** table keys **********/

    /**
     * @var string
     */
    protected $sessions_key = 'sessions';

    /**
     * DBStorage constructor.
     * @param PDO $pdo_instance
     * @param int $expire_time
     * @param int $suspend_time
     * @param string $namespace
     * @param ConfigParser $config_parser
     * @param array $crypt_keys
     * @throws \Sim\Crypt\Exceptions\CryptException
     * @throws IDBException
     */
    public function __construct(
        PDO $pdo_instance,
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

        $this->pdo = $pdo_instance;
        $this->db = new DB($this->pdo);
        $this->cookie = new Cookie($this->crypt);
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     * @throws CookieException
     * @throws \Exception
     */
    public function store(array $credentials)
    {
        $agent = new Agent();

        // get some fields
        $uuid = $this->generateUUID();
        $userId = $this->getUserID($credentials);
        $ip = AuthUtil::getIPAddress();
        $device = $agent->device();
        $browser = $agent->browser();
        $platform = $agent->platform();
        $expireAt = $this->expire_time;
        $createdAt = time();

        $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
        if ($this->db->insert(
            $this->tables[$this->sessions_key],
            [
                $sessionColumns['uuid'] => $uuid,
                $sessionColumns['user_id'] => $userId,
                $sessionColumns['ip_address'] => $ip,
                $sessionColumns['device'] => $device,
                $sessionColumns['browser'] => $browser,
                $sessionColumns['platform'] => $platform,
                $sessionColumns['expire_at'] => $expireAt,
                $sessionColumns['created_at'] => $createdAt,
            ]
        )) {
            $setCookie = new SetCookie(
                $this->exp_key,
                json_encode([
                    'id' => $userId,
                    'uuid' => $uuid,
                ]),
                time() + $this->expire_time,
                '/',
                null,
                null,
                true
            );
            $this->cookie->set($setCookie);
            $this->setStatus(IAuth::STATUS_ACTIVE);

            $this->updateSuspendTime();
        }
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
        if ($this->hasExpired() || !$this->evaluateStorageValue()) return $this;

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
     * Destroy session
     * @return bool
     * @throws IDBException
     */
    public function destroy(): bool
    {
        // if uuid is available from restored value
        $sessionUUID = $this->restore();
        if (!is_null($sessionUUID) && isset($sessionUUID['uuid'])) {
            $sessionUUID = $sessionUUID['uuid'];
        } else {
            return false;
        }

        // check for uuid validation
        if (UUIDUtil::isValid($sessionUUID)) {
            $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
            $this->db->delete(
                $this->tables[$this->sessions_key],
                "{$sessionColumns['uuid']}=:uuid",
                [
                    'uuid' => $sessionUUID,
                ]
            );
            return true;
        }

        return false;
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function generateUUID(): string
    {
        return UUIDUtil::v4();
    }

    /**
     * @param array $credentials
     * @return int|null
     * @throws IDBException
     * @throws ConfigException
     */
    protected function getUserID(array $credentials): ?int
    {
        $userId = null;

        $userColumns = $this->config_parser->getTablesColumn($this->users_key);
        $credentialColumns = $this->config_parser->getCredentialColumns();
        $user = $this->db->getFrom(
            $this->tables[$this->users_key],
            "{$this->db->quoteName($credentialColumns['username'])}=:u",
            $userColumns['id'],
            ['u' => $credentials['username']]);
        if (count($user)) {
            $userId = $user[0][$userColumns['id']];
        }

        return $userId;
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
        $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
        $userSess = $this->db->getFrom(
            $this->tables[$this->sessions_key],
            "{$sessionColumns['uuid']}=:u",
            $sessionColumns['user_id'],
            [
                'u' => $restoredVal['uuid'],
            ]
        );

        if (count($userSess) !== 1) return false;

        $userId = $userSess[0][$sessionColumns['user_id']];

        $where = "{$userColumns['id']}=:u";
        $bindValues = [
            'u' => $userId,
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
        $verified = $this->verifier->verify($restoredVal['password'] ?? '', $password);

        if ($verified) return true;

        return false;
    }
}