<?php

namespace Sim\Auth\Abstracts;

use PDO;
use Sim\Auth\Config\ConfigParser;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Helpers\DB;
use Sim\Auth\Interfaces\IDBException;

abstract class AbstractBaseAuth
{
    /**
     * @var PDO $pdo
     */
    protected $pdo;

    /**
     * @var DB
     */
    protected $db;

    /**
     * @var array $default_config
     */
    protected $default_config = [];

    /**
     * @var array $config
     */
    protected $config = [];

    /**
     * @var array $tables
     */
    protected $tables = [];

    /**
     * @var ConfigParser
     */
    protected $config_parser;

    /**
     * @var array
     * Format:
     * [
     *   'username' => provided username column by user,
     *   'password' => provided password column by user,
     * ]
     */
    protected $credential_columns = [];

    /********** table keys **********/

    /**
     * @var string
     */
    protected $api_keys_key = 'api_keys';

    /**
     * @var string
     */
    protected $api_key_role_key = 'api_key_role';

    /**
     * AbstractBaseAuth constructor.
     * @param PDO $pdo_instance
     * @param array|null $config
     * @throws IDBException
     */
    public function __construct(PDO $pdo_instance, ?array $config = null)
    {
        $this->pdo = $pdo_instance;
        $this->db = new DB($pdo_instance);

        // load default config from _Config dir
        $this->default_config = include __DIR__ . '/../_Config/config.php';
        if (!is_null($config)) {
            $this->setConfig($config);
        } else {
            $this->setConfig($this->default_config);
        }
    }

    /**
     * @param array $config
     * @param bool $merge_config
     * @return static
     * @throws IDBException
     * @throws ConfigException
     */
    public function setConfig(array $config, bool $merge_config = false)
    {
        if ($merge_config) {
            if (!empty($config)) {
                $this->config = array_merge_recursive($this->default_config, $config);
            }
        } else {
            $this->config = $config;
        }

        // parse config
        $this->config_parser = new ConfigParser($this->config, $this->pdo);

        // get tables
        $this->tables = $this->config_parser->getTables();

        // get credential columns
        $this->credential_columns = $this->config_parser->getCredentialColumns();

        return $this;
    }

    /**
     * @return static
     */
    public function runConfig()
    {
        $this->config_parser->up();
        return $this;
    }
}