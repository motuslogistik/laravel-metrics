<?php

namespace motuslogistik\Metrics\Metrics;

use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\PendingMetric;

class Counter extends PendingMetric
{
    public function record(): void
    {
        $this->store()->incr($this->getKey());
        $this->registerType(Type::Counter);
    }
}
