<?php

namespace Sim\Auth;

use PDO;

class DB
{
    /**
     * @var PDO
     */
    protected $pdo;

    /**
     * DB constructor.
     * @param PDO $pdo
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param string $table_name
     * @return bool
     */
    public function createTableIfNotExists(string $table_name): bool
    {

    }

    /**
     * @param string $table_name
     * @param string $column_name
     * @param string $column_type
     * @return bool
     */
    public function createColumnIfNotExists(string $table_name, string $column_name, string $column_type): bool
    {

    }

    /**
     * @param string $table_name
     * @param array $constraints
     */
    public function addConstraint(string $table_name, array $constraints)
    {

    }

    /**
     * @return array
     */
    public function getFrom(): array
    {

    }

    /**
     * @param string $tbl1
     * @param string $tbl2
     * @param string $on
     * @param string $where
     * @return array
     */
    public function getFromJoin(string $tbl1, string $tbl2, string $on, string $where): array
    {

    }

    /**
     * @param string $table_name
     * @param $where
     * @return bool
     */
    public function insert(string $table_name, $where): bool
    {

    }

    /**
     * @param string $table_name
     * @param $where
     * @return bool
     */
    public function update(string $table_name, $where): bool
    {

    }

    /**
     * @param string $table_name
     * @param $where
     * @return bool
     */
    public function delete(string $table_name, $where): bool
    {

    }

    /**
     * @param string $table_name
     * @param $where
     * @return int
     */
    public function count(string $table_name, $where): int
    {

    }
}