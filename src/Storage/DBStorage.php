<?php

namespace Sim\Auth\Storage;

use Jenssegers\Agent\Agent;
use PDO;
use Sim\Auth\Abstracts\AbstractStorage;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\DB;
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
     * @var DB
     */
    protected $db;

    /**
     * @var ICookie
     */
    protected $cookie;

    /**
     * @var string
     */
    protected $storage_name = '__Sim_Auth_DB__';

    /**
     * @var array
     */
    protected $tables;

    /********** table keys **********/

    /**
     * @var string
     */
    protected $users_key = 'users';

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
        $this->exp_key = $this->storage_name . '-' . $this->namespace . '-uuid';
        $this->sus_key = $this->storage_name . '-' . $this->namespace . '-suspend_time';

        $this->tables = $this->config_parser->getTables();
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
                json_encode(['uuid' => $uuid]),
                time() + $this->expire_time,
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
        if (!$this->hasExpired()) {
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
        if ($res) {
            $this->setStatus(IAuth::STATUS_SUSPEND);
        }
        return $res;
    }

    /**
     * Destroy session
     * @param string $session_uuid
     * @return static
     * @throws CookieException
     * @throws IDBException
     */
    public function destroy(string $session_uuid)
    {
        if (UUIDUtil::isValid($session_uuid)) {
            $sessionColumns = $this->config_parser->getTablesColumn($this->sessions_key);
            if ($this->db->delete(
                $this->tables[$this->sessions_key],
                "{$sessionColumns['uuid']}=:uuid",
                [
                    'uuid' => $session_uuid,
                ]
            )) {
                $this->delete();
            }
        }
        return $this;
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
     * @throws \Sim\Auth\Exceptions\ConfigException
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
}