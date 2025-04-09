<?php

namespace App\Core;

use App\Core\Database;
use App\Core\QueryBuilder;

abstract class Model
{
    protected $table;
    protected $connection = 'mysql'; // ค่าเริ่มต้นของการเชื่อมต่อฐานข้อมูล
    protected $queryBuilder;

    public function __construct($connection = null)
    {
        if (php_sapi_name() === 'cli' && getenv('APP_ENV') === 'testing') {
            $this->connection = 'sqlite';
        } elseif ($connection) {
            // ใช้ connection ที่ระบุไว้
            $this->connection = $connection;
        }

        // สร้าง QueryBuilder สำหรับโมเดลนี้
        $pdo = Database::getInstance($this->connection);
        $this->queryBuilder = new QueryBuilder($pdo, $this->table);
    }

    public function all()
    {
        return $this->queryBuilder->select()->get();
    }

    public function find($id)
    {
        return $this->queryBuilder->select()->where('id', '=', $id)->first();
    }

    public function create($data)
    {
        $id = $this->queryBuilder->insert($data); // สร้างข้อมูลและรับ ID ที่เพิ่งสร้าง
        return $this->find($id); // ดึงข้อมูลผู้ใช้ที่เพิ่งสร้างและคืนค่า
    }

    public function update($id, $data)
    {
        return $this->queryBuilder->where('id', '=', $id)->update($data);
    }

    public function delete($id)
    {
        return $this->queryBuilder->where('id', '=', $id)->delete();
    }

    public function where($column, $operator = '=', $value = null)
    {
        return $this->queryBuilder->where($column, $operator, $value);
    }

    public function paginate($perPage = 10, $currentPage = 1, $filters = [], $orderBy = null)
    {
        $offset = ($currentPage - 1) * $perPage;

        // เพิ่มเงื่อนไขการกรองข้อมูล (where)
        $query = $this->queryBuilder->select();
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
        $totalQuery = $this->queryBuilder->select('COUNT(*) as total');
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
}