<?php

namespace App\Core;

use App\Core\QueryBuilder;
use App\Core\Collection;
use Exception;

abstract class Model
{
    protected $table;
    protected $connection = 'mysql';
    protected $primaryKey = 'id';
    protected bool $allowDynamic = false;
    protected array $attributes = [];
    protected array $fillable = [];
    protected array $hidden = [];
    protected array $with = [];
    protected $queryBuilder;

    public function __construct($attributes = [], $connection = null)
    {
        if (php_sapi_name() === 'cli' && getenv('APP_ENV') === 'testing') {
            $this->connection = 'sqlite';
        } elseif ($connection) {
            $this->connection = $connection;
        }

        $this->fill($attributes);
    }

    protected function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $pdo = app('db')->getConnection($this->connection);
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

        if ($this->with) {
            $this->loadRelations([$this]);
        }

        return $data;
    }

    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            if ($this->allowDynamic || in_array($key, $this->fillable, true)) {
                $this->attributes[$key] = $value;
            }
        }
        return $this;
    }

    public function __get($name)
    {
        return $this->attributes[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    public function getAttributes()
    {
        return $this->attributes;
    }

    public static function query()
    {
        return (new static)->getQueryBuilder();
    }

    public static function all()
    {
        return static::query()->select()->get();
    }

    public static function find($id)
    {
        return static::query()->select()->where((new static)->primaryKey, '=', $id)->first();
    }

    public static function findOrFail($id)
    {
        $model = static::find($id);
        if (!$model) {
            throw new Exception(static::class . " not found.");
        }
        return $model;
    }

    public static function create(array $data)
    {
        $instance = new static();
        $id = $instance->getQueryBuilder()->insert($data);
        return static::find($id);
    }

    public static function update($id, array $data)
    {
        return static::query()->where((new static)->primaryKey, '=', $id)->update($data);
    }

    public static function delete($id)
    {
        return static::query()->where((new static)->primaryKey, '=', $id)->delete();
    }

    public static function where($column, $operator = '=', $value = null)
    {
        return static::query()->where($column, $operator, $value);
    }

    public static function paginate($perPage = 10, $currentPage = 1, $filters = [], $orderBy = null)
    {
        $query = static::query()->select();

        if (!empty($filters)) {
            foreach ($filters as $column => $value) {
                $query->where($column, '=', $value);
            }
        }

        if ($orderBy) {
            $query->orderBy($orderBy['column'], $orderBy['direction'] ?? 'asc');
        }

        $total = $query->count();
        $data = $query->limit($perPage)
                     ->offset(($currentPage - 1) * $perPage)
                     ->get();

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

    public static function rawQuery($sql, $params = [])
    {
        return static::query()->rawQuery($sql, $params);
    }

    public static function rawExec($sql, $params = [])
    {
        return static::query()->rawExec($sql, $params);
    }

    public static function rawQueryAsModel($sql, $params = [])
    {
        return static::query()->rawQueryAsModel($sql, $params, static::class);
    }

    public static function getPdo()
    {
        return static::query()->getPdo();
    }

    public function with(array|string $relations)
    {
        $this->with = is_array($relations) ? $relations : [$relations];
        return $this;
    }

    public function loadRelations(array $models)
    {
        foreach ($this->with as $relation) {
            if (method_exists($this, $relation)) {
                foreach ($models as $model) {
                    $model->{$relation} = $model->{$relation}();
                }
            }
        }
        return $models;
    }

    public function load(string $relation)
    {
        if (method_exists($this, $relation)) {
            $this->{$relation} = $this->{$relation}();
        }
    }

    // ความสัมพันธ์แบบ hasOne
    public function hasOne($related, $foreignKey, $localKey = null)
    {
        $localKey = $localKey ?? $this->primaryKey;
        return (new $related)->where($foreignKey, '=', $this->{$localKey})->first();
    }

    // ความสัมพันธ์แบบ hasMany
    public function hasMany($related, $foreignKey, $localKey = null)
    {
        $localKey = $localKey ?? $this->primaryKey;
        return (new $related)->where($foreignKey, '=', $this->{$localKey})->get();
    }

    // ความสัมพันธ์แบบ belongsTo
    public function belongsTo($related, $foreignKey, $ownerKey = 'id')
    {
        return (new $related)->where($ownerKey, '=', $this->{$foreignKey})->first();
    }

    // ความสัมพันธ์แบบ belongsToMany
    public function belongsToMany($related, $pivotTable, $foreignPivotKey, $relatedPivotKey, $localKey = null, $relatedKey = 'id')
    {
        $localKey = $localKey ?? $this->primaryKey;

        $pivotResults = static::getPdo()->prepare("SELECT {$relatedPivotKey} FROM {$pivotTable} WHERE {$foreignPivotKey} = ?");
        $pivotResults->execute([$this->{$localKey}]);
        $ids = array_column($pivotResults->fetchAll(\PDO::FETCH_ASSOC), $relatedPivotKey);

        if (empty($ids)) return new Collection([]);

        return (new $related)->whereIn($relatedKey, $ids)->get();
    }
}
