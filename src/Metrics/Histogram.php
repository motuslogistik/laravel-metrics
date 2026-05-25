<?php

namespace motuslogistik\Metrics\Metrics;

use Closure;
use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\PendingMetric;

class Histogram extends PendingMetric
{
    public function record(int|float $value): void
    {
        Metrics::histogram($this->name)->record($value, $this->attributes());
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
