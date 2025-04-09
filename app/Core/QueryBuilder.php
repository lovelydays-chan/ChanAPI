<?php

namespace App\Core;

use PDO;

class QueryBuilder
{
    private $pdo;
    private $table;
    private $query;
    private $bindings = [];
    private $whereClause = '';

    public function __construct(PDO $pdo, $table)
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    public function select($columns = '*')
    {
        $this->query = "SELECT {$columns} FROM {$this->table}";
        return $this;
    }

    public function where($column, $operator = '=', $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $condition) {
                [$field, $op, $val] = $condition;
                $this->addWhereClause($field, $op, $val);
            }
        } else {
            $this->addWhereClause($column, $operator, $value);
        }

        return $this;
    }

    private function addWhereClause($column, $operator, $value)
    {
        if (empty($this->whereClause)) {
            $this->whereClause = " WHERE {$column} {$operator} ?";
        } else {
            $this->whereClause .= " AND {$column} {$operator} ?";
        }
        $this->bindings[] = $value;
    }

    public function get()
    {
        $this->query .= $this->whereClause;
        $stmt = $this->pdo->prepare($this->query);
        $stmt->execute($this->bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first()
    {
        $this->query .= $this->whereClause . " LIMIT 1";
        $stmt = $this->pdo->prepare($this->query);
        $stmt->execute($this->bindings);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($sql);

        // ตรวจสอบว่าค่าที่ส่งไปตรงกับ placeholders หรือไม่
        $stmt->execute(array_values($data));

        return $this->pdo->lastInsertId();
    }

    public function update($data)
    {
        // สร้างคำสั่ง SET
        $set = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($data)));

        // สร้างคำสั่ง SQL
        $this->query = "UPDATE {$this->table} SET {$set}" . $this->whereClause;

        // รวมค่าของ SET และ WHERE
        $bindings = array_merge(array_values($data), $this->bindings);

        // เตรียมและดำเนินการคำสั่ง SQL
        $stmt = $this->pdo->prepare($this->query);
        return $stmt->execute($bindings);
    }

    public function delete()
    {
        $this->query = "DELETE FROM {$this->table}" . $this->whereClause;

        $stmt = $this->pdo->prepare($this->query);
        return $stmt->execute($this->bindings);
    }

    public function execute()
    {
        $stmt = $this->pdo->prepare($this->query);
        return $stmt->execute($this->bindings);
    }

    public function limit($limit)
    {
        $this->query .= " LIMIT {$limit}";
        return $this;
    }

    public function offset($offset)
    {
        $this->query .= " OFFSET {$offset}";
        return $this;
    }

    public function orderBy($column, $direction = 'asc')
    {
        $this->query .= " ORDER BY {$column} " . strtoupper($direction);
        return $this;
    }
}