<?php

namespace Sim\Auth\Storage;

use Sim\Auth\Abstracts\AbstractStorage;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IDBException;
use Sim\Auth\Utils\AuthUtil;
use Sim\Crypt\Exceptions\CryptException;
use Sim\Session\ISession;
use Sim\Session\Session;

class SessionStorage extends AbstractStorage
{
    /**
     * @var ISession
     */
    protected $session;

    /**
     * SessionStorage constructor.
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

        $this->session = new Session($this->crypt);

        // start session if not started yet
        $this->session->start();
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function store(array $credentials)
    {
        $userId = $this->getUserID($credentials);
        $ip = AuthUtil::getIPAddress();

        $this->session->setTimed($this->exp_key, \array_merge($credentials, ['id' => $userId, 'ip' => $ip]), $this->expire_time);
        $this->setStatus(IAuth::STATUS_ACTIVE);

        $this->updateSuspendTime();

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function restore(): ?array
    {
        $expireVal = $this->session->getTimed($this->exp_key, null);
        return $expireVal;
    }

    /**
     * {@inheritdoc}
     */
    public function delete()
    {
        $this->session->removeTimed($this->sus_key);
        $this->session->removeTimed($this->exp_key);
        $this->setStatus(IAuth::STATUS_NONE);
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws IDBException
     */
    public function updateSuspendTime()
    {
        if (!$this->hasExpired() && $this->evaluateStorageValue()) {
            $this->session->setTimed($this->sus_key, 'suspend_val', $this->suspend_time);
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
        $res = \is_null($expireVal);

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
        $suspendVal = $this->session->getTimed($this->sus_key, null);
        $res = \is_null($suspendVal);

        if (IAuth::STATUS_ACTIVE === $this->getStatus() && $res) {
            $this->setStatus(IAuth::STATUS_SUSPEND);
        }

        return $res;
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
        if (\count($user)) {
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

        if (\count($user) !== 1) return false;

        // check for stored ip as well
        $ip = AuthUtil::getIPAddress();
        if($ip !== $restoredVal['ip']) return false;

        $password = $user[0][$userColumns['password']];

        // if we do not have any password to verify,
        // then we do not have any verifier
        if (\is_null($this->verifier)) return true;

        // verify password with user's password in db
        $verified = $this->verifier->verify($restoredVal['password'] ?? '', $password);

        if ($verified) return true;

        return false;
    }
}