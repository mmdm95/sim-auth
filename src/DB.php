<?php

namespace Sim\Auth;

use PDO;
use PDOStatement;
use Sim\Auth\Exceptions\BindValueException;
use Sim\Auth\Interfaces\IDBException;

class DB
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * @var array
     */
    protected $quote_arr = ['`', '`'];

    /**
     * @var string
     */
    protected $quote_find = '"';

    /**
     * @var string
     */
    protected $quote_replace = '""';

    /**
     * @var array
     */
    protected static $quote_arr_static = ['`', '`'];

    /**
     * @var string
     */
    protected static $quote_find_static = '"';

    /**
     * @var string
     */
    protected static $quote_replace_static = '""';

    /**
     * @var string
     */
    protected $db_name;

    /**
     * @var string
     */
    protected $db_driver;

    /**
     * @var string
     * @see https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
     */
    protected $collation = 'utf8mb4_unicode_ci';

    /**
     * DB constructor.
     * @param PDO $pdo
     * @param bool $change_db_collation
     * @throws IDBException
     */
    public function __construct(PDO $pdo, bool $change_db_collation = false)
    {
        $this->pdo = $pdo;
        $this->db_name = $this->getDBName();
        $this->db_driver = $this->getCurrentDriver();
        $this->setQuoteParams();

        if ($change_db_collation) {
            $this->changeDBCollation();
        }
    }

    /**
     * @param string $table_name
     * @param string $column_name
     * @param $column_info
     * @return bool
     * @throws IDBException
     */
    public function createTableIfNotExists(string $table_name, string $column_name, $column_info): bool
    {
        $quotedTable = $this->quoteNames($table_name);
        $quotedColumnName = $this->quoteNames($column_name);

        if ('sqlsrv' === $this->db_driver) {
            $sql = "IF NOT EXISTS (SELECT * FROM sysobjects WHERE name={$quotedTable} AND xtype='U)";
            $sql .= " CREATE TABLE {$quotedTable} ({$quotedColumnName} {$column_info})";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS {$quotedTable} ";
            $sql .= "({$quotedColumnName} {$column_info});";
        }

        $res = $this->exe($sql);

        // change table collation
        $this->changeTableCollation($table_name);

        return $res;
    }

    /**
     * @see https://stackoverflow.com/a/29428841/12154893 - for mysql query
     * @param string $table_name
     * @param string $column_name
     * @param string $column_type
     * @return bool
     * @throws IDBException
     */
    public function createColumnIfNotExists(string $table_name, string $column_name, string $column_type): bool
    {
        $stringDBName = "'" . $this->db_name . "'";
        $stringTableName = "'" . $table_name . "'";
        $stringColumnName = "'" . $column_name . "'";

        $quotedTableName = $this->quoteName($table_name);
        $quotedColumnName = $this->quoteName($column_name);

        if ('sqlsrv' === $this->db_driver) {
            $sql = "IF NOT EXISTS (SELECT * FROM {$this->quoteName('INFORMATION_SCHEMA.COLUMNS')} ";
            $sql .= "WHERE {$this->quoteName('TABLE_SCHEMA')}={$stringDBName} AND ";
            $sql .= "{$this->quoteName('TABLE_NAME')}={$stringTableName} AND ";
            $sql .= "{$this->quoteName('COLUMN_NAME')}={$stringColumnName}) ";
            $sql .= "BEGIN ALTER TABLE {$quotedTableName} ADD {$quotedColumnName} {$column_type} ";
            $sql .= "END;";
        } else {
            $sql = "IF NOT EXISTS (SELECT NULL FROM {$this->quoteName('INFORMATION_SCHEMA.COLUMNS')} ";
            $sql .= "WHERE {$this->quoteName('TABLE_SCHEMA')}={$stringDBName} AND ";
            $sql .= "{$this->quoteName('TABLE_NAME')}={$stringTableName} AND ";
            $sql .= "{$this->quoteName('COLUMN_NAME')}={$stringColumnName}) ";
            $sql .= "THEN ALTER TABLE {$quotedTableName} ADD {$quotedColumnName} {$column_type}; ";
            $sql .= "END IF;";
        }

        return $this->exe($sql);
    }

    /**
     * @param string $table_name
     * @param string $constraint
     * @return bool
     * @throws IDBException
     */
    public function addConstraint(string $table_name, string $constraint): bool
    {
        $sql = "ALTER TABLE {$this->quoteName($table_name)}";
        $sql .= " {$constraint}";
        return $this->exe($sql);
    }

    /**
     * You should quote $where yourself
     *
     * @param string $table_name
     * @param string|null $where
     * @param array|string $columns
     * @param array $bind_values
     * @return array
     * @throws IDBException
     */
    public function getFrom(
        string $table_name,
        ?string $where = null,
        $columns = '*',
        array $bind_values = []
    ): array
    {
        $columns = $this->quoteNames($columns);
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        $sql = "SELECT {$columns} FROM {$this->quoteName($table_name)}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }
        return $this->exec($sql, $bind_values)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * You should quote $on and $where yourself
     *
     * @param string $join_type
     * @param string $tbl1
     * @param string $tbl2
     * @param string $on
     * @param string|null $where
     * @param array|string $columns
     * @param array $bind_values
     * @return array
     * @throws IDBException
     */
    public function getFromJoin(
        string $join_type,
        string $tbl1,
        string $tbl2,
        string $on,
        ?string $where = null,
        $columns = '*',
        array $bind_values = []
    ): array
    {
        $columns = $this->quoteNames($columns);
        if (is_array($columns)) {
            $columns = implode(', ', $columns);
        }

        $sql = "SELECT {$columns} FROM {$this->quoteName($tbl1)} " .
            strtoupper($join_type) . " JOIN {$this->quoteName($tbl2)} ON {$on}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        return $this->exec($sql, $bind_values)->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * You should quote $query yourself.
     * Please use named parameters.
     *
     * Exp.
     *   $query: SELECT * FROM tbl WHERE x=:x1 AND y=:y1
     *   $bind_values: [
     *                   x1 => 1,
     *                   y1 => 2
     *                 ]
     *
     * @param string $query
     * @param array $bind_values
     * @return PDOStatement
     * @throws IDBException
     */
    public function exec(string $query, array $bind_values = []): PDOStatement
    {
        $stmt = $this->prepareAndBind($query, $bind_values);
        $stmt->execute();
        return $stmt;
    }

    /**
     * Exp.
     *   $table_name: tbl
     *   $values: [
     *              column1 => actual value for column1,
     *              column2 => actual value for column2,
     *              ...
     *            ]
     *
     * @param string $table_name
     * @param array $values
     * @return bool
     * @throws IDBException
     */
    public function insert(string $table_name, array $values): bool
    {
        $columns = array_keys($values);
        $values = array_values($values);

        $namedParameters = [];
        $bindValues = [];
        foreach ($values as $key => $value) {
            $k = 'id' . $key;
            $namedParameters[] = ':' . $k;
            $bindValues[$k] = $value;
        }

        $sql = "INSERT INTO {$this->quoteName($table_name)} (" .
            implode(', ', $columns) .
            ") VALUES (" .
            implode(', ', $namedParameters) .
            ") ";

        return $this->exe($sql, $bindValues);
    }

    /**
     * You should quote $where yourself.
     *
     * @param string $table_name
     * @param array $values
     * @param string|null $where
     * @param array $bind_values
     * @return bool
     * @throws IDBException
     */
    public function update(string $table_name, array $values, ?string $where = null, array $bind_values = []): bool
    {
        $i = 1;
        $namedParameters = [];
        $bindValues = [];
        foreach ($values as $column => $value) {
            $k = 'id' . $i;
            $namedParameters[] = $column . '=:' . $k;
            $bindValues[$k] = $value;
        }

        $sql = "UPDATE {$this->quoteName($table_name)} SET (" .
            implode(',', $namedParameters) .
            ")";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $this->pdo->beginTransaction();
        $res = $this->exe($sql, array_merge($bindValues, $bind_values));
        if ($res) {
            $this->pdo->commit();
        } else {
            $this->pdo->rollBack();
        }
        return $res;
    }

    /**
     * You should quote $where yourself.
     *
     * @param string $table_name
     * @param string $where
     * @param array $bind_values
     * @return bool
     * @throws IDBException
     */
    public function delete(string $table_name, string $where, array $bind_values = []): bool
    {
        $sql = "DELETE FROM {$this->quoteName($table_name)} WHERE {$where}";

        $this->pdo->beginTransaction();
        $res = $this->exe($sql, $bind_values);
        if ($res) {
            $this->pdo->commit();
        } else {
            $this->pdo->rollBack();
        }
        return $res;
    }

    /**
     * You should quote $where yourself.
     *
     * @param string $table_name
     * @param string|null $where
     * @param array $bind_values
     * @return int
     * @throws IDBException
     */
    public function count(string $table_name, ?string $where = null, array $bind_values = []): int
    {
        $sql = "SELECT COUNT(*) AS {$this->quoteName('count')} FROM {$this->quoteName($table_name)}";
        if (!empty($where)) {
            $sql .= " WHERE {$where}";
        }

        $res = $this->exec($sql, $bind_values)->fetchAll(PDO::FETCH_ASSOC);
        return (int)$res['count'];
    }

    /**
     * @param string $table_name
     * @param array $values
     * @param string|null $where
     * @param array $bind_values
     * @return bool
     * @throws IDBException
     */
    public function updateIfExistsOrInsert(string $table_name, array $values, ?string $where = null, array $bind_values = []): bool
    {
        // begin a transaction
        $this->pdo->beginTransaction();

        // get count first
        $count = $this->count($table_name, $where, $bind_values);
        // if there is an item, then update it
        if ($count > 0) {
            $res = $this->update($table_name, $values, $where, $bind_values);
        } else { // otherwise insert it
            $res = $this->insert($table_name, $values);
        }

        // commit or rollback transaction
        if ($res) {
            $this->pdo->commit();
        } else {
            $this->pdo->rollBack();
        }
        return $res;
    }

    /**
     * @param string $name
     * @return string
     */
    public function quoteName(string $name): string
    {
        return self::quoteSingleName($name);
    }

    /**
     * @param string $name
     * @return string
     */
    public static function quoteSingleName(string $name): string
    {
        $name = trim(explode(' AS ', $name)[0]);
        if (false !== strpos($name, '.')) {
            return implode(
                '.',
                array_map(
                    'self::quoteSingleName',
                    explode('.', $name)
                )
            );
        }

        $name = str_replace(self::$quote_find_static, self::$quote_replace_static, $name);
        return self::$quote_arr_static[0] . $name . self::$quote_arr_static[1];
    }

    /**
     * @throws IDBException
     */
    public function changeDBCollation(): void
    {
        if ('sqlsrv' === $this->db_driver) {
            $sql = "ALTER DATABASE {$this->quoteName($this->db_name)} COLLATE {$this->collation};";
        } else {
            $sql = "ALTER DATABASE {$this->quoteName($this->db_name)} CHARACTER SET utf8mb4 COLLATE {$this->collation};";
        }

        $this->exec($sql);
    }

    /**
     * @param string $query
     * @param array $bindValues
     * @return PDOStatement
     * @throws IDBException
     */
    protected function prepareAndBind(string $query, array $bindValues = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($query);
        foreach ($bindValues as $key => $val) {
            $this->bind($stmt, $key, $val);
        }
        return $stmt;
    }

    /**
     * @param \PDOStatement $stmt
     * @param $key
     * @param $val
     * @return bool
     * @throws IDBException
     */
    protected function bind(\PDOStatement $stmt, $key, $val): bool
    {
        if (is_int($val)) {
            return $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }
        if (is_bool($val)) {
            return $stmt->bindValue($key, $val, PDO::PARAM_BOOL);
        }
        if (is_null($val)) {
            return $stmt->bindValue($key, $val, PDO::PARAM_NULL);
        }
        if (!is_scalar($val)) {
            $type = gettype($val);
            throw new BindValueException(
                "Cannot bind value of type '{$type}' to placeholder '{$key}'"
            );
        }

        return $stmt->bindValue($key, $val);
    }

    /**
     * @param $names
     * @return array|string
     */
    protected function quoteNames($names)
    {
        if ('*' === $names) {
            return $names;
        }

        if (is_string($names) && false === strpos($names, $this->quote_arr[0])) {
            return $this->quoteName($names);
        }

        if (is_array($names)) {
            foreach ($names as &$name) {
                if (is_string($name) && false === strpos($name, $this->quote_arr[0])) {
                    $name = $this->quoteName($name);
                }
            }
        }

        return $names;
    }

    /**
     * Get current database name
     *
     * @return string
     * @throws IDBException
     */
    private function getDBName(): string
    {
        $stmt = $this->exec('SELECT database()');
        return (string)$stmt->fetchAll(PDO::FETCH_COLUMN)[0];
    }

    /**
     * set quote according to driver
     */
    private function setQuoteParams(): void
    {
        switch ($this->db_driver) {
            case 'mysql':
                $this->quote_arr = ['`', '`'];
                $this->quote_find = '`';
                $this->quote_replace = '``';
                //-----
                self::$quote_arr_static = ['`', '`'];
                self::$quote_find_static = '`';
                self::$quote_replace_static = '``';
                return;
            case 'sqlsrv':
                $this->quote_arr = ['[', ']'];
                $this->quote_find = ']';
                $this->quote_replace = '][';
                //-----
                self::$quote_arr_static = ['[', ']'];
                self::$quote_find_static = ']';
                self::$quote_replace_static = '][';
                return;
            default:
                $this->quote_arr = ['"', '"'];
                $this->quote_find = '"';
                $this->quote_replace = '""';
                //-----
                self::$quote_arr_static = ['"', '"'];
                self::$quote_find_static = '"';
                self::$quote_replace_static = '""';
                return;
        }
    }

    /**
     * Get current PDO driver
     *
     * @see https://stackoverflow.com/a/10090754/12154893
     * @return string
     */
    private function getCurrentDriver(): ?string
    {
        return strtolower($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?? '');
    }

    /**
     * @param string $table_name
     * @throws IDBException
     */
    private function changeTableCollation(string $table_name): void
    {
        $sql = "ALTER TABLE {$this->quoteName($table_name)} CONVERT TO CHARACTER SET utf8mb4 COLLATE {$this->collation};";
        $this->exec($sql);
    }

    /**
     * @param string $query
     * @param array $bind_values
     * @return bool
     * @throws IDBException
     */
    private function exe(string $query, array $bind_values = []): bool
    {
        $stmt = $this->prepareAndBind($query, $bind_values);
        return $stmt->execute();
    }
}