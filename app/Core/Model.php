<?php

namespace App\Core;

use App\Core\QueryBuilder;
use App\Core\DatabaseManager;

abstract class Model
{
    protected $table;
    protected $connection = 'mysql';
    protected $queryBuilder;
    protected $primaryKey = 'id';
    protected bool $allowDynamic = false;
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $hidden = [];

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
            $pdo = app(DatabaseManager::class)->getConnection($this->connection);
            $this->queryBuilder = new QueryBuilder($pdo, $this->table, static::class);
        }
        return $this->queryBuilder;
    }
    public function toArray()
    {
        $data = $this->attributes;

        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }

        return $data;
    }
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            // ตรวจสอบว่า key ใน fillable หรือ dynamic property
            if ($this->allowDynamic || in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }
    // ฟังก์ชัน __get() ใช้เพื่อดึงค่า
    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    // ฟังก์ชัน __set() ใช้เพื่อกำหนดค่า
    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }
    // ฟังก์ชัน getAttributes() เพื่อดึงข้อมูลทั้งหมด
    public function getAttributes()
    {
        return $this->attributes;
    }
    public function all()
    {
        return $this->getQueryBuilder()->select()->get();
    }

    public function find($id)
    {
        return $this->getQueryBuilder()->select()->where('id', '=', $id)->first();
    }

    public function create(array $data)
    {
        $id = $this->getQueryBuilder()->insert($data);
        return $this->find($id); // ค้นหาข้อมูลหลังจากสร้าง
    }

    public function update($id, array $data)
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

        $query = $this->getQueryBuilder()->select();
        // เพิ่มเงื่อนไข where
        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                $query->where($column, '=', $value);
            }
        }

        // เพิ่มการจัดเรียง (orderBy)
        if ($orderBy) {
            $query->orderBy($orderBy['column'], $orderBy['direction'] ?? 'asc');
        }

        // ดึงข้อมูลหน้า
        $data = $query->limit($perPage)
            ->offset($offset)
            ->get();

        // คำนวณจำนวนข้อมูลทั้งหมด
        $totalQuery = $this->getQueryBuilder()->select('COUNT(*) as total');
        foreach ($filters as $column => $value) {
            $totalQuery->where($column, '=', $value);
        }

        $total = $totalQuery->get()[0]->total ?? 0;

        return [
            'data' => $data->toArray(),
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

    public function getPdo()
    {
        return $this->getQueryBuilder()->getPdo();
    }
}
