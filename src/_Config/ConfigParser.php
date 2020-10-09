<?php

namespace Sim\Auth\Config;

use PDO;
use Sim\Auth\DB;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Interfaces\IDBException;

class ConfigParser
{
    /**
     * @var DB
     */
    protected $db;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $credentials_key = 'credential_columns';

    /**
     * @var string
     */
    protected $credential_username_key = 'username';

    /**
     * @var string
     */
    protected $credential_password_key = 'password';

    /**
     * @var string
     */
    protected $blueprint_key = 'blueprints';

    /**
     * @var string
     */
    protected $table_name_key = 'table_name';

    /**
     * @var string
     */
    protected $columns_key = 'columns';

    /**
     * @var string
     */
    protected $types_key = 'types';

    /**
     * @var string
     */
    protected $constraints_key = 'constraints';

    /**
     * @var array
     */
    protected $table_aliases = [
        'users', 'roles', 'resources', 'user_role',
        'role_res_perm', 'user_res_perm', 'sessions'
    ];

    /**
     * @var array
     */
    protected $tables = [];

    /**
     * @var array
     */
    protected $tables_columns = [];

    /**
     * @var array
     */
    protected $structures = [];

    /**
     * @var array
     */
    protected $credentials = [];

    /**
     * ConfigParser constructor.
     * @param array $config
     * @param PDO $pdo
     * @throws IDBException
     */
    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->parse();
        $this->db = new DB($pdo, true);
    }

    /**
     * @return static
     * @throws IDBException
     */
    public function up()
    {
        if (!empty($this->structures)) {
            // iterate through all tables for their structure
            foreach ($this->structures as $tableName => $items) {
                // create table is not exists
                $this->db->createTableIfNotExists($tableName);
                // iterate through all columns and create column if not exists
                foreach ($items[$this->columns_key] as $columnName) {
                    if (isset($items[$this->types_key][$columnName]) &&
                        is_string($items[$this->types_key][$columnName]) &&
                        !empty($items[$this->types_key][$columnName])) {
                        $this->db->createColumnIfNotExists($tableName, $columnName, $items[$this->types_key][$columnName]);
                    }
                }
                // iterate through all constraint and add it to table
                foreach ($items[$this->constraints_key] as $constraint) {
                    if (is_string($constraint) && !empty($constraint)) {
                        $this->db->addConstraint($tableName, $constraint);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getTables(): array
    {
        return $this->tables;
    }

    /**
     * In following format:
     * [
     *   'table alias' => [
     *     columns1, column2, ...
     *   ]
     *   ...
     * ]
     *
     * @return array
     */
    public function getTablesColumns(): array
    {
        return $this->tables_columns;
    }

    /**
     * Array of columns' name
     *
     * @param $table_alias
     * @return array
     */
    public function getTablesColumn($table_alias): array
    {
        return $this->tables_columns[$table_alias] ?? [];
    }

    /**
     * Return value will be as following format:
     * [
     *   'username' => provided username column by user,
     *   'password' => provided password column by user,
     * ]
     *
     * @return array
     */
    public function getCredentialColumns(): array
    {
        return $this->credentials;
    }

    /**
     * Parse config file
     * @throws ConfigException
     */
    protected function parse(): void
    {
        if (!empty($this->config)) {
            foreach ($this->config as $key => $structures) {
                if ($key === $this->constraints_key) { // if it wat credentials columns key
                    if (!isset($structures[$this->credential_username_key], $structures[$this->credential_password_key]) ||
                        empty($structures[$this->credential_username_key]) || empty($structures[$this->credential_password_key])) {
                        throw new ConfigException('Username/Password column is not set or it is empty!');
                    }
                    // store credential columns
                    $this->credentials[$this->credential_username_key] = $structures[$this->credential_username_key];
                    $this->credentials[$this->credential_password_key] = $structures[$this->credential_password_key];
                } elseif ($key === $this->blueprint_key) { // if it was blueprints key
                    // iterate through all blueprints
                    foreach ($structures as $tableAlias => $structure) {
                        // if alias is in aliases array
                        if (in_array($tableAlias, $this->table_aliases)) {
                            // get tables
                            $this->tables[$tableAlias] = $structure[$this->table_name_key] ?? '';
                            // get tables and other structure
                            $this->structures[$tableAlias] = [
                                $this->columns_key => array_values($structure[$this->columns_key] ?? []),
                                $this->types_key => array_values($structure[$this->types_key] ?? []),
                                $this->constraints_key => array_values($structure[$this->constraints_key] ?? []),
                            ];
                            // get tables keys that should be fixed
                            $this->tables_columns[$tableAlias] = array_keys($structure[$this->columns_key] ?? []);
                        }
                    }
                }
            }
        }
    }
}