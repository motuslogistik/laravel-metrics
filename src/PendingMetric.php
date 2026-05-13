<?php

namespace motuslogistik\Metrics;

use BackedEnum;
use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Enums\Type;
use motuslogistik\Metrics\Metrics\Counter;
use motuslogistik\Metrics\Metrics\Gauge;

class PendingMetric
{
    const RESERVED = '|;=';

    public readonly string $name;

    protected array $labels = [];

    protected bool $global = false;

    public function __construct(string|BackedEnum $name)
    {
        $this->name = $this->normalize($name);
        $this->validateString($this->name);
    }

    public function label(string|BackedEnum $name, string|BackedEnum $value): static
    {
        $name = $this->normalize($name);
        $value = $this->normalize($value);

        $this->validateString($name);
        $this->validateString($value);

        $this->labels[$name] = $value;

        return $this;
    }

    public function global(bool $value = true): static
    {
        $this->global = $value;

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
        $metric->global = $this->global;

        return $metric;
    }

    protected function getKey(): string
    {
        $key = Metrics::prefix().$this->name;
        if (empty($this->labels)) {
            return $key;
        }

        $labels = $this->labels;
        ksort($labels);

        $result = array_map(fn ($k, $v) => "$k=$v", array_keys($labels), array_values($labels));

        return $key.';'.implode(';', $result);
    }

    protected function store(): Store
    {
        if ($this->global) {
            $store = Metrics::globalStore();

            if ($store === null) {
                throw new \RuntimeException(
                    'No global store configured. Set metrics.global_store to use ->global().'
                );
            }

            return $store;
        }

        return Metrics::store();
    }

    protected function registerType(Type $type): void
    {
        $this->store()->set(
            Metrics::prefix().'__types|'.$this->name,
            $type->value,
        );
    }

    protected function normalize(string|BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }

    protected function validateString(string $string): void
    {
        if (strpbrk($string, self::RESERVED) !== false) {
            throw new \InvalidArgumentException('Value '.$string.' contains reserved characters: '.self::RESERVED);
        }
    }
}
