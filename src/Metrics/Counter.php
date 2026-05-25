<?php

namespace motuslogistik\Metrics\Metrics;

use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\PendingMetric;

class Counter extends PendingMetric
{
    public function incr(int|float $amount = 1): void
    {
        Metrics::upDownCounter($this->name)->add($amount, $this->attributes());
    }

    public function decr(int|float $amount = 1): void
    {
        Metrics::upDownCounter($this->name)->add(-$amount, $this->attributes());
    }

    public function record(): void
    {
        $this->incr();
    }
}
