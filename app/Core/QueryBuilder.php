<?php
namespace App\Core;

use PDO;
use App\Core\Collection;

class QueryBuilder
{
    private $pdo;
    private $table;
    private $modelClass;
    private $selects = '*';
    private $wheres = [];
    private $bindings = [];
    private $orders = [];
    private $limitValue;
    private $offsetValue;
    private $with = [];
    private $orWheres = []; // สำหรับ whereOr

    public function __construct(PDO $pdo, string $table, $modelClass = null)
    {
        $this->pdo = $pdo;
        $this->table = $table;
        $this->modelClass = $modelClass;
    }

    public function select($columns = '*')
    {
        $this->selects = is_array($columns) ? implode(', ', $columns) : $columns;
        return $this;
    }

    // where(), whereIn(), whereOr() แบบ fluent chainable
    public function where($column, $operator = '=', $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $condition) {
                [$col, $op, $val] = $condition;
                $this->wheres[] = [$col, $op, $val];
                $this->bindings[] = $val;
            }
        } else {
            $this->wheres[] = [$column, $operator, $value];
            $this->bindings[] = $value;
        }
        return $this;
    }

    // whereIn() - สำหรับการ query with IN condition
    public function whereIn($column, array $values)
    {
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $this->wheres[] = "{$column} IN ({$placeholders})";
        $this->bindings = array_merge($this->bindings, $values);
        return $this;
    }

    // whereOr() - สำหรับการ query OR condition
    public function whereOr($column, $operator = '=', $value = null)
    {
        if (is_array($column)) {
            foreach ($column as $condition) {
                [$col, $op, $val] = $condition;
                $this->orWheres[] = [$col, $op, $val];
                $this->bindings[] = $val;
            }
        } else {
            $this->orWheres[] = [$column, $operator, $value];
            $this->bindings[] = $value;
        }
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC')
    {
        $this->orders[] = "{$column} " . strtoupper($direction);
        return $this;
    }

    public function limit(int $limit)
    {
        $this->limitValue = $limit;
        return $this;
    }

    public function offset(int $offset)
    {
        $this->offsetValue = $offset;
        return $this;
    }

    public function with(array $relations)
    {
        $this->with = $relations;
        return $this;
    }

    public function get()
    {
        $stmt = $this->prepareStatement();
        $stmt->execute($this->bindings);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->resetState();

        $models = $this->mapToModels($results);

        if ($this->with) {
            foreach ($models as $model) {
                $model->loadRelations($this->with);
            }
        }

        return new Collection($models);
    }

    public function first()
    {
        $this->limit(1);
        $stmt = $this->prepareStatement();
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->resetState();

        if (!$result) return null;

        $model = $this->mapToModel($result);

        if ($this->with) {
            $model->loadRelations($this->with);
        }

        return $model;
    }

    public function insert(array $data)
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ({$columns}) VALUES ({$placeholders})";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));

        $this->resetState();

        return $this->pdo->lastInsertId();
    }

    public function update(array $data)
    {
        $set = implode(', ', array_map(fn($key) => "{$key} = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET {$set}" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute(array_merge(array_values($data), $this->bindings));

        $this->resetState();

        return $result;
    }

    public function delete()
    {
        $sql = "DELETE FROM {$this->table}" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $result = $stmt->execute($this->bindings);

        $this->resetState();

        return $result;
    }

    public function save($modelInstance)
    {
        if (isset($modelInstance->id)) {
            return $this->update($modelInstance->getAttributes());
        } else {
            return $this->insert($modelInstance->getAttributes());
        }
    }

    public function rawQuery(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rawExec(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function rawQueryAsModel(string $sql, array $params = [], ?string $modelClass = null)
    {
        if (!$modelClass) {
            throw new \InvalidArgumentException('Model class is required for rawQueryAsModel().');
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return new Collection(array_map(fn($row) => $this->createModelInstance($row, $modelClass), $results));
    }

    private function prepareStatement()
    {
        $sql = "SELECT {$this->selects} FROM {$this->table}"
             . $this->buildWhereClause()
             . $this->buildOrderClause()
             . $this->buildLimitOffset();

        return $this->pdo->prepare($sql);
    }

    private function buildWhereClause()
    {
        if (empty($this->wheres) && empty($this->orWheres)) return '';

        $clauses = array_map(fn($w) => "{$w[0]} {$w[1]} ?", $this->wheres);
        $orClauses = array_map(fn($w) => "{$w[0]} {$w[1]} ?", $this->orWheres);

        $clauses = array_merge($clauses, $orClauses);

        return ' WHERE ' . implode(' AND ', $clauses);
    }

    private function buildOrderClause()
    {
        return empty($this->orders) ? '' : ' ORDER BY ' . implode(', ', $this->orders);
    }

    private function buildLimitOffset()
    {
        $sql = '';
        if ($this->limitValue !== null) $sql .= " LIMIT {$this->limitValue}";
        if ($this->offsetValue !== null) $sql .= " OFFSET {$this->offsetValue}";
        return $sql;
    }

    private function mapToModels(array $results)
    {
        return $this->modelClass
            ? array_map(fn($row) => $this->mapToModel($row), $results)
            : $results;
    }

    private function mapToModel(array $row)
    {
        return $this->modelClass
            ? $this->createModelInstance($row, $this->modelClass)
            : $row;
    }

    private function createModelInstance(array $row, string $modelClass)
    {
        $model = new $modelClass();

        if (!($model instanceof \App\Core\Model)) {
            throw new \Exception("Class {$modelClass} must extend App\Core\Model");
        }
        return $model->fill($row);
    }

    public function count(): int
    {
        $originalSelects = $this->selects;
        $sql = "SELECT COUNT(*) as total FROM {$this->table}" . $this->buildWhereClause();

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->selects = $originalSelects;
        $this->resetState();

        return (int) ($result['total'] ?? 0);
    }

    private function resetState(): void
    {
        $this->selects = '*';
        $this->wheres = [];
        $this->orWheres = [];
        $this->bindings = [];
        $this->orders = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->with = [];
    }

    public function clear(): self
    {
        $this->resetState();
        return $this;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getPdo()
    {
        return $this->pdo;
    }
}
