<?php

namespace App\Core;

use JsonSerializable;
use ArrayAccess;
use Countable;
use IteratorAggregate;
use Traversable;
use Closure;

class Collection implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    protected array $items = [];

    protected static array $macros = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    public static function macro(string $name, callable $callback): void
    {
        static::$macros[$name] = $callback;
    }

    public function __call(string $method, array $parameters): mixed
    {
        if (isset(static::$macros[$method])) {
            return call_user_func_array(static::$macros[$method]->bindTo($this, static::class), $parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    public function all(): array
    {
        return $this->items;
    }

    public function first(?callable $callback = null, mixed $default = null): mixed
    {
        if (is_null($callback)) {
            return $this->items[0] ?? $default;
        }

        foreach ($this->items as $item) {
            if ($callback($item)) {
                return $item;
            }
        }

        return $default;
    }

    public function groupBy(string|Closure $key): static
    {
        $results = [];

        foreach ($this->items as $item) {
            $groupKey = is_callable($key)
                ? $key($item)
                : (is_array($item) ? $item[$key] : ($item->$key ?? null));

            $results[$groupKey][] = $item;
        }

        return new static($results);
    }

    public function chunk(int $size): static
    {
        return new static(array_chunk($this->items, $size));
    }

    public function sort(?callable $callback = null): static
    {
        $items = $this->items;
        $callback ? usort($items, $callback) : sort($items);
        return new static($items);
    }

    public function reduce(callable $callback, mixed $initial = null): mixed
    {
        return array_reduce($this->items, $callback, $initial);
    }

    public function merge(array|self $items): static
    {
        $merged = $this->items;

        if ($items instanceof self) {
            $merged = array_merge($merged, $items->all());
        } else {
            $merged = array_merge($merged, $items);
        }

        return new static($merged);
    }

    public function unique(?callable $callback = null): static
    {
        $seen = [];
        $result = [];

        foreach ($this->items as $item) {
            $key = $callback ? $callback($item) : $item;

            if (!in_array($key, $seen, true)) {
                $seen[] = $key;
                $result[] = $item;
            }
        }

        return new static($result);
    }

    public function map(callable $callback): static
    {
        return new static(array_map($callback, $this->items));
    }

    public function filter(?callable $callback = null): static
    {
        return new static(array_values(array_filter($this->items, $callback)));
    }

    public function pluck(string $key): static
    {
        $results = [];

        foreach ($this->items as $item) {
            if (is_array($item)) {
                $results[] = $item[$key] ?? null;
            } elseif (is_object($item)) {
                $results[] = $item->$key ?? null;
            }
        }

        return new static($results);
    }

    public function contains(callable|string $keyOrCallback, mixed $value = null): bool
    {
        if (is_callable($keyOrCallback)) {
            foreach ($this->items as $item) {
                if ($keyOrCallback($item)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($this->items as $item) {
            if (is_array($item) && ($item[$keyOrCallback] ?? null) === $value) {
                return true;
            }
            if (is_object($item) && ($item->{$keyOrCallback} ?? null) === $value) {
                return true;
            }
        }

        return false;
    }

    public function flatten(): static
    {
        $results = [];

        array_walk_recursive($this->items, function ($value) use (&$results) {
            $results[] = $value;
        });

        return new static($results);
    }

    // JsonSerializable
    public function jsonSerialize(): mixed
    {
        return $this->items;
    }

    // ArrayAccess
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->items[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        is_null($offset)
            ? $this->items[] = $value
            : $this->items[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->items[$offset]);
    }

    // Countable
    public function count(): int
    {
        return count($this->items);
    }

    // IteratorAggregate
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->items);
    }
    public function toArray() {
        return array_map(function ($item) {
            if (method_exists($item, 'toArray')) {
                return $item->toArray();
            }
            return (array) $item;
        }, $this->items);
    }
}
