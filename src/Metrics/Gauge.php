<?php

namespace motuslogistik\Metrics\Metrics;

use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\PendingMetric;

class Gauge extends PendingMetric
{
    public function record(int|float $value): void
    {
        $this->store()->set($this->getKey(), $value);
        $this->registerType(Type::Gauge);
    }
}
