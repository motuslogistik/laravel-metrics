<?php

namespace motuslogistik\Metrics\Stores;

use Generator;
use motuslogistik\Metrics\Contracts\Store;
use Swoole\Table;

class SwooleTableStore implements Store
{
    private Table $values;
    private Table $strings;

    public function __construct(int $size = 4096, int $stringSize = 64)
    {
        $this->values = new Table($size);
        $this->values->column('v', Table::TYPE_FLOAT);
        $this->values->create();

        $this->strings = new Table($size);
        $this->strings->column('v', Table::TYPE_STRING, $stringSize);
        $this->strings->create();
    }

    public function incr(string $key)
    {
        $this->values->incr($key, 'v');
    }

    public function decr(string $key)
    {
        $this->values->decr($key, 'v');
    }

    public function get(string $key)
    {
        if ($this->values->exists($key)) {
            return $this->values->get($key, 'v');
        }

        if ($this->strings->exists($key)) {
            return $this->strings->get($key, 'v');
        }

        return false;
    }

    public function set(string $key, $value)
    {
        if (is_numeric($value)) {
            $this->values->set($key, ['v' => $value]);
            return;
        }

        $this->strings->set($key, ['v' => (string) $value]);
    }

    public function has(string $key)
    {
        return $this->values->exists($key) || $this->strings->exists($key);
    }

    public function clear(string $key)
    {
        $this->values->del($key);
        $this->strings->del($key);
    }

    public function iterator(?string $prefix = null): Generator
    {
        foreach ($this->values as $key => $row) {
            if ($prefix === null || str_starts_with($key, $prefix)) {
                yield $key => $row['v'];
            }
        }

        foreach ($this->strings as $key => $row) {
            if ($prefix === null || str_starts_with($key, $prefix)) {
                yield $key => $row['v'];
            }
        }
    }
}
