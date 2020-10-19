<?php

namespace Sim\Auth\Config;

use PDO;
use Sim\Auth\Exceptions\ConfigException;
use Sim\Auth\Helpers\DB;
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
        'users', 'roles', 'api_keys', 'resources', 'user_role',
        'api_key_role', 'role_res_perm', 'user_res_perm', 'sessions'
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
     * ConfigParser constructor.
     * @param array $config
     * @param PDO $pdo
     * @throws IDBException
     */
    public function __construct(array $config, PDO $pdo)
    {
        $this->config = $config;
        $this->parse();
        $this->db = new DB($pdo);
    }

    /**
     * @return static
     */
    public function up()
    {
        // change database collation
        try {
            $this->db->changeDBCollation();
        } catch (IDBException $e) {
            // do nothing
        } catch (\Exception $e) {
            // do nothing
        }

        if (!empty($this->structures)) {
            // iterate through all tables for their structure
            foreach ($this->structures as $tableName => $items) {
                // create table is not exists
                try {
                    $this->db->createTableIfNotExists(
                        $tableName,
                        $items[$this->columns_key]['id'],
                        $items[$this->types_key]['id']
                    );
                } catch (IDBException $e) {
                    // do nothing
                } catch (\Exception $e) {
                    // do nothing
                }

                // iterate through all columns and create column if not exists
                foreach ($items[$this->columns_key] as $columnKey => $columnName) {
                    $typeKey = $items[$this->types_key][$columnKey] ?? null;
                    if (
                        'id' !== $columnKey &&
                        is_string($typeKey) &&
                        !empty($typeKey)
                    ) {
                        try {
                            $this->db->createColumnIfNotExists($tableName, $columnName, $typeKey);
                        } catch (IDBException $e) {
                            // do nothing
                        } catch (\Exception $e) {
                            // do nothing
                        }
                    }
                }
            }

            // iterate through all tables for their constraint(s)
            foreach ($this->structures as $tableName => $items) {
                if (isset($items[$this->constraints_key])) {
                    foreach ($items[$this->constraints_key] as $constraint) {
                        if (is_string($constraint) && !empty($constraint)) {
                            try {
                                $this->db->addConstraint($tableName, $constraint);
                            } catch (IDBException $e) {
                                // do nothing
                            } catch (\Exception $e) {
                                // do nothing
                            }
                        }
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
     * @throws ConfigException
     */
    public function getCredentialColumns(): array
    {
        $usersColumns = $this->getTablesColumn($this->tables['users']);

        if (!isset($usersColumns['username'], $usersColumns['password']) ||
            empty($usersColumns['username']) ||
            empty($usersColumns['password'])
        ) {
            throw new ConfigException('Users table should have [username] and [password] columns\' key');
        }

        return [
            'username' => $usersColumns['username'],
            'password' => $usersColumns['password'],
        ];
    }

    /**
     * Return value will be as following format:
     * [
     *   'username' => provided username column by user,
     *   'api_key' => provided api key column by user,
     * ]
     *
     * @return array
     * @throws ConfigException
     */
    public function getAPICredentialColumns(): array
    {
        $usersColumns = $this->getTablesColumn($this->tables['api_keys']);

        if (!isset($usersColumns['username'], $usersColumns['api_key']) ||
            empty($usersColumns['username']) ||
            empty($usersColumns['api_key'])
        ) {
            throw new ConfigException('API users table should have [username] and [api_key] columns\' key');
        }

        return [
            'username' => $usersColumns['username'],
            'api_key' => $usersColumns['api_key'],
        ];
    }

    /**
     * Parse config file
     */
    protected function parse(): void
    {
        if (!empty($this->config)) {
            foreach ($this->config as $key => $structures) {
                if ($key === $this->blueprint_key) { // if it was blueprints key
                    // iterate through all blueprints
                    foreach ($structures as $tableAlias => $structure) {
                        // if alias is in aliases array
                        if (in_array($tableAlias, $this->table_aliases)) {
                            // get tables
                            $this->tables[$tableAlias] = $structure[$this->table_name_key] ?? '';
                            // get tables and other structure
                            $this->structures[$tableAlias] = [
                                $this->columns_key => $structure[$this->columns_key] ?? [],
                                $this->types_key => $structure[$this->types_key] ?? [],
                                $this->constraints_key => $structure[$this->constraints_key] ?? [],
                            ];
                            // get tables keys that should be fixed
                            $this->tables_columns[$tableAlias] = $structure[$this->columns_key] ?? [];
                        }
                    }
                }
            }
        }
    }
}