<?php

namespace motuslogistik\Metrics;

use BackedEnum;
use motuslogistik\Metrics\Metrics\Counter;
use motuslogistik\Metrics\Metrics\Gauge;
use motuslogistik\Metrics\Metrics\Histogram;

class PendingMetric
{
    public readonly string $name;

    /** @var array<string, string> */
    protected array $labels = [];

    public function __construct(string|BackedEnum $name)
    {
        $this->name = $this->normalize($name);
    }

    public function label(string|BackedEnum $name, string|BackedEnum $value): static
    {
        $this->labels[$this->normalize($name)] = $this->normalize($value);

        return $this;
    }

    /**
     * No-op kept for backward compatibility. With OTel, aggregation across
     * processes/hosts happens at the Collector — every metric is effectively
     * "global" once flushed.
     */
    public function global(bool $value = true): static
    {
        return $this;
    }

    public function counter(): Counter
    {
        return $this->as(Counter::class);
    }

    public function gauge(): Gauge
    {
        return $this->as(Gauge::class);
    }

    public function histogram(): Histogram
    {
        return $this->as(Histogram::class);
    }

    /**
     * @template T of PendingMetric
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function as(string $class): PendingMetric
    {
        $metric = new $class($this->name);
        $metric->labels = $this->labels;

        return $metric;
    }

    /**
     * @return array<string, string>
     */
    protected function attributes(): array
    {
        return $this->labels;
    }

    protected function normalize(string|BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
