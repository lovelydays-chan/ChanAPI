<?php

namespace App\Core;

use App\Core\Database;
use App\Core\QueryBuilder;

abstract class Model
{
    protected $table;
    protected $connection = 'mysql';
    protected $queryBuilder;

    public function __construct($connection = null)
    {
        if (php_sapi_name() === 'cli' && getenv('APP_ENV') === 'testing') {
            $this->connection = 'sqlite';
        } elseif ($connection) {
            $this->connection = $connection;
        }
    }
    protected function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $pdo = Database::getInstance($this->connection);
            $this->queryBuilder = new QueryBuilder($pdo, $this->table);
        }
        return $this->queryBuilder;
    }
    public function all(): Collection
    {
        return $this->getQueryBuilder()->select()->get();
    }

    public function find($id)
    {
        return $this->getQueryBuilder()->select()->where('id', '=', $id)->first();
    }

    public function create(array $data)
    {
        $id = $this->getQueryBuilder()->insert($data); // สร้างข้อมูลและรับ ID ที่เพิ่งสร้าง
        return $this->find($id); // ดึงข้อมูลผู้ใช้ที่เพิ่งสร้างและคืนค่า
    }

    public function update($id, $data)
    {
        return $this->getQueryBuilder()->where('id', '=', $id)->update($data);
    }

    public function delete($id)
    {
        return $this->getQueryBuilder()->where('id', '=', $id)->delete();
    }

    public function where($column, $operator = '=', $value = null)
    {
        return $this->getQueryBuilder()->where($column, $operator, $value);
    }

    public function paginate($perPage = 10, $currentPage = 1, $filters = [], $orderBy = null)
    {
        $offset = ($currentPage - 1) * $perPage;

        // เพิ่มเงื่อนไขการกรองข้อมูล (where)
        $query = $this->getQueryBuilder()->select();
        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                $query = $query->where($column, '=', $value);
            }
        }

        // เพิ่มการจัดเรียงข้อมูล (orderBy)
        if ($orderBy) {
            $query = $query->orderBy($orderBy['column'], $orderBy['direction'] ?? 'asc');
        }

        // ดึงข้อมูลสำหรับหน้าปัจจุบัน
        $data = $query
            ->limit($perPage)
            ->offset($offset)
            ->get();

        // นับจำนวนรายการทั้งหมด (รวมเงื่อนไข where)
        $totalQuery = $this->getQueryBuilder()->select('COUNT(*) as total');
        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                $totalQuery = $totalQuery->where($column, '=', $value);
            }
        }
        $total = $totalQuery->get()[0]['total'];

        return [
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'last_page' => ceil($total / $perPage),
            ],
        ];
    }
    public function rawQuery($sql, $params = [])
    {
        return $this->getQueryBuilder()->rawQuery($sql, $params);
    }
    public function rawExec($sql, $params = [])
    {
        return $this->getQueryBuilder()->rawExec($sql, $params);
    }
    public function rawQueryAsModel($sql, $params = [])
    {
        return $this->getQueryBuilder()->rawQueryAsModel($sql, $params, static::class);
    }
    public function raw(string $sql, array $params = []): Collection
    {
        return $this->queryBuilder->rawQueryAsModel($sql, $params, static::class);
    }

    public function getPdo()
    {
        return $this->getQueryBuilder()->getPdo();
    }
}
