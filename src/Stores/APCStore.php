<?php

namespace motuslogistik\Metrics\Stores;

use APCUIterator;
use Generator;
use motuslogistik\Metrics\Contracts\Store;

class APCStore implements Store
{
    public function incr(string $key, int|float $amount = 1)
    {
        apcu_inc($key, (int) $amount);
    }

    public function decr(string $key, int|float $amount = 1)
    {
        apcu_dec($key, (int) $amount);
    }

    public function get(string $key)
    {
        return apcu_fetch($key);
    }

    public function set(string $key, $value)
    {
        apcu_store($key, $value);
    }

    public function has(string $key)
    {
        return apcu_exists($key);
    }

    public function clear(string $key)
    {
        apcu_delete($key);
    }

    public function iterator(?string $prefix = null): Generator
    {
        foreach (new APCUIterator('/^'.$prefix.'/') as $curr) {
            yield $curr['key'] => $curr['value'];
        }
    }
}
