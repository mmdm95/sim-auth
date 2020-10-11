<?php

namespace Sim\Auth\Abstracts;

use Sim\Auth\Config\ConfigParser;
use Sim\Auth\DB;
use Sim\Auth\Interfaces\IAuth;
use Sim\Auth\Interfaces\IStorage;
use Sim\Crypt\Crypt;
use Sim\Crypt\Exceptions\CryptException;
use Sim\Crypt\ICrypt;

abstract class AbstractStorage implements IStorage
{
    /**
     * @var DB
     */
    protected $db = null;

    /**
     * @var ConfigParser
     */
    protected $config_parser;

    /**
     * @var ICrypt
     */
    protected $crypt = null;

    /**
     * @var int
     */
    protected $expire_time;

    /**
     * @var int
     */
    protected $suspend_time;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @var int
     */
    protected $status = IAUTH::STATUS_NONE;

    /**
     * @var string
     */
    protected $storage_name;

    /**
     * @var string
     */
    protected $exp_key;

    /**
     * @var string
     */
    protected $sus_key;

    /**
     * AbstractStorage constructor.
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
        $this->expire_time = $expire_time;
        $this->suspend_time = $suspend_time;
        $this->namespace = $namespace;
        $this->config_parser = $config_parser;

        if (
            isset($crypt_keys['main'], $crypt_keys['assured']) &&
            !empty($crypt_keys['main']) &&
            !empty($crypt_keys['assured'])
        ) {
            $this->crypt = new Crypt($crypt_keys['main'], $crypt_keys['assured']);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(int $status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): int
    {
        $this->hasExpired();
        $this->hasSuspended();
        return $this->status;
    }
}