<?php

namespace motuslogistik\Metrics\Metrics;

use Closure;
use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\PendingMetric;

class Histogram extends PendingMetric
{
    public function record(int|float $value): void
    {
        $this->store()->set($this->getKey(), $value);
        $this->registerType(Type::Histogram);
    }

    /**
     * @template TReturn
     * @param Closure(): TReturn $fn
     * @return TReturn
     */
    public function observe(Closure $fn): mixed
    {
        $start = microtime(true);
        $result = $fn();
        $duration = round((microtime(true) - $start) * 1000);

        $this->record($duration);

        return $result;
    }
}
