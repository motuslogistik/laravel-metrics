<?php

namespace motuslogistik\Metrics\Contracts;

interface Store
{
    public function incr(string $key, int|float $amount = 1);

    public function decr(string $key, int|float $amount = 1);

    public function get(string $key);

    public function set(string $key, $value);

    public function has(string $key);

    public function clear(string $key);

    public function iterator(?string $prefix = null);
}
