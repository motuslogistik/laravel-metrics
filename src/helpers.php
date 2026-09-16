<?php

use motuslogistik\Metrics\Hooks\Observer;
use motuslogistik\Metrics\Metrics\Counter;
use motuslogistik\Metrics\Metrics\Gauge;
use motuslogistik\Metrics\Metrics\Histogram;
use motuslogistik\Metrics\PendingMetric;
use motuslogistik\Metrics\Tracing\Span;

if (! function_exists('motuslogistik_metrics_apply_labels')) {
    /**
     * @template T of PendingMetric
     *
     * @param  T  $metric
     * @param  array<string|int, string|BackedEnum>  $labels
     * @return T
     */
    function motuslogistik_metrics_apply_labels(PendingMetric $metric, array $labels): PendingMetric
    {
        foreach ($labels as $key => $value) {
            $metric->label((string) $key, $value);
        }

        return $metric;
    }
}

if (! function_exists('metric')) {
    function metric(string|BackedEnum $name, array $labels = []): PendingMetric
    {
        return motuslogistik_metrics_apply_labels(new PendingMetric($name), $labels);
    }
}

if (! function_exists('counter')) {
    function counter(string|BackedEnum $name, array $labels = []): Counter
    {
        return motuslogistik_metrics_apply_labels(new Counter($name), $labels);
    }
}

if (! function_exists('gauge')) {
    function gauge(string|BackedEnum $name, array $labels = []): Gauge
    {
        return motuslogistik_metrics_apply_labels(new Gauge($name), $labels);
    }
}

if (! function_exists('histogram')) {
    function histogram(string|BackedEnum $name, array $labels = []): Histogram
    {
        return motuslogistik_metrics_apply_labels(new Histogram($name), $labels);
    }
}

if (! function_exists('observe')) {
    /**
     * Auto-instrument a method call with a latency histogram. Requires the
     * `opentelemetry` PHP extension; logs a warning and no-ops otherwise.
     *
     * @param  class-string  $class
     */
    function observe(string $class, string $method, ?string $name = null): Observer
    {
        return new Observer($class, $method, $name);
    }
}

if (! function_exists('span')) {
    /**
     * Trace a closure as an OTel span. Unlike the metric helpers, the span name
     * is used verbatim — `metrics.prefix` is a Prometheus convention and has no
     * meaning for traces.
     */
    function span(string|BackedEnum $name): Span
    {
        return new Span($name);
    }
}
