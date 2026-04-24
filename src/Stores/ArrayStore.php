<?php

namespace motuslogistik\Metrics\Stores;

use Generator;
use motuslogistik\Metrics\Contracts\Store;

class ArrayStore implements Store
{
    private array $data = [];

    public function incr(string $key)
    {
        if (! array_key_exists($key, $this->data)) {
            $this->data[$key] = 1;
        } else {
            $this->data[$key] += 1;
        }
    }

    public function decr(string $key)
    {
        if (! array_key_exists($key, $this->data)) {
            $this->data[$key] = 0;
        } else {
            $this->data[$key] -= 1;
        }
    }

    public function get(string $key)
    {
        return $this->data[$key] ?? false;
    }

    public function set(string $key, $value)
    {
        $this->data[$key] = $value;
    }

    public function has(string $key)
    {
        return array_key_exists($key, $this->data);
    }

    public function clear(string $key)
    {
        unset($this->data[$key]);
    }

    public function iterator(?string $prefix = null): Generator
    {
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                yield $key => $value;
            }
        }
    }
}
