<?php

namespace motuslogistik\Metrics\Metrics;

use BackedEnum;
use Closure;
use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\PendingMetric;
use Throwable;

class Histogram extends PendingMetric
{
    /**
     * @param  array<string, string|BackedEnum>  $labels  Extra labels merged into this recording only.
     */
    public function record(int|float $value, array $labels = []): void
    {
        Metrics::histogram($this->name)->record($value, $this->attributes($labels));
    }

    /**
     * Time the given closure and record its duration in seconds (float).
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $fn
     * @param  array<string, string|BackedEnum>  $appendOnSuccess  Labels added when the closure returns.
     * @param  array<string, string|BackedEnum>  $appendOnFailure  Labels added when the closure throws.
     * @return TReturn
     */
    public function time(
        Closure $fn,
        array $appendOnSuccess = [],
        array $appendOnFailure = [],
    ): mixed {
        $start = microtime(true);

        try {
            $result = $fn();
        } catch (Throwable $e) {
            $this->record(microtime(true) - $start, $appendOnFailure);

            throw $e;
        }

        $this->record(microtime(true) - $start, $appendOnSuccess);

        return $result;
    }
}
