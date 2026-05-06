<?php

namespace motuslogistik\Metrics\Metrics;

use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\PendingMetric;

class Counter extends PendingMetric
{
    public function set(int|float $value): void
    {
        $this->store()->set($this->getKey(), $value);
        $this->registerType(Type::Counter);
    }

    public function incr(int|float $amount = 1): void
    {
        $this->store()->incr($this->getKey(), $amount);
        $this->registerType(Type::Counter);
    }

    public function decr(int|float $amount = 1): void
    {
        $this->store()->decr($this->getKey(), $amount);
        $this->registerType(Type::Counter);
    }

    public function record(): void
    {
        $this->incr();
    }
}
