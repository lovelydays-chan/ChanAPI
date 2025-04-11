<?php

namespace App\Core;

use Exception;

abstract class BaseRepository
{
    protected $model;
    protected $pdo;

    public function __construct()
    {
        $this->model = $this->model();
        $this->pdo = $this->model->getPdo(); // ดึง PDO จาก Model
    }


    abstract public function model();

    public function all()
    {
        return $this->model->all();
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update($id, array $data)
    {
        return $this->model->update($id, $data);
    }

    public function delete($id)
    {
        return $this->model->delete($id);
    }

    public function where($column, $operator = '=', $value = null)
    {
        return $this->model->where($column, $operator, $value);
    }

    public function paginate($perPage = 10, $currentPage = 1, $filters = [], $orderBy = null)
    {
        return $this->model->paginate($perPage, $currentPage, $filters, $orderBy);
    }
    public function beginTransaction()
    {
        $this->pdo->beginTransaction();
    }

    public function commit()
    {
        $this->pdo->commit();
    }

    public function rollback()
    {
        $this->pdo->rollBack();
    }

    public function transaction(callable $callback)
    {
        try {
            $this->beginTransaction();
            $result = $callback($this); // ส่ง repo เข้า callback ด้วย
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
}
