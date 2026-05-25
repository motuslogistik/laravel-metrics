<?php

namespace motuslogistik\Metrics\Metrics;

use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\PendingMetric;

class Gauge extends PendingMetric
{
    public function record(int|float $value): void
    {
        Metrics::gauge($this->name)->record($value, $this->attributes());
    }

    public function set(int|float $value): void
    {
        $this->record($value);
    }
}
