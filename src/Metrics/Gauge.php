<?php

namespace motuslogistik\Metrics\Metrics;

use Closure;
use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\PendingMetric;

class Gauge extends PendingMetric
{
    public function record(int|float $value): void
    {
        $this->store()->set($this->getKey(), $value);
        $this->registerType(Type::Gauge);
    }

    /**
     * Time the given closure and record its duration in seconds (float).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $fn
     * @return TReturn
     */
    public function observe(Closure $fn): mixed
    {
        $start = microtime(true);
        $result = $fn();
        $this->record(microtime(true) - $start);

        return $result;
    }
}
