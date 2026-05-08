<?php

namespace motuslogistik\Metrics\Exporters;

use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Metrics;

class PrometheusExporter
{
    /** @var list<Store> */
    private array $stores;

    public function __construct(Store|array $stores)
    {
        $this->stores = $stores instanceof Store ? [$stores] : array_values($stores);
    }

    public function render(): string
    {
        $prefix = Metrics::prefix();
        $typePrefix = $prefix.'__types|';

        $types = [];
        $series = [];

        foreach ($this->stores as $store) {
            [$storeTypes, $storeSeries] = $this->collect($store, $prefix, $typePrefix);

            $types += $storeTypes;

            foreach ($storeSeries as $metricName => $samples) {
                $series[$metricName] ??= [];
                array_push($series[$metricName], ...$samples);
            }
        }

        $output = '';

        foreach ($series as $metricName => $samples) {
            if (isset($types[$metricName])) {
                $output .= "# TYPE {$metricName} {$types[$metricName]}\n";
            }

            foreach ($samples as [$labels, $value]) {
                $output .= $this->formatLine($metricName, $labels, $value);
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, list<array{0: array<string, string>, 1: mixed}>>}
     */
    private function collect(Store $store, string $prefix, string $typePrefix): array
    {
        $types = [];
        $series = [];

        foreach ($store->iterator($prefix) as $key => $value) {
            if (str_starts_with($key, $typePrefix)) {
                $metricName = substr($key, strlen($typePrefix));
                $types[$metricName] = (string) $value;

                continue;
            }

            [$metricName, $labels] = $this->parseKey($key, $prefix);
            $series[$metricName] ??= [];
            $series[$metricName][] = [$labels, $value];
        }

        return [$types, $series];
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function parseKey(string $key, string $prefix): array
    {
        $tail = substr($key, strlen($prefix));       // "orders_created;status=paid"

        $parts = explode(';', $tail);
        $metricName = array_shift($parts);           // "orders_created"
        $labels = [];

        foreach ($parts as $kv) {
            [$k, $v] = explode('=', $kv, 2);
            $labels[$k] = $v;
        }

        return [$metricName, $labels];
    }

    /**
     * @param  array<string, string>  $labels
     */
    private function formatLine(string $metricName, array $labels, mixed $value): string
    {
        $renderedLabels = '';
        if ($labels !== []) {
            $pairs = [];
            foreach ($labels as $k => $v) {
                $pairs[] = $k.'="'.$this->escapeLabelValue($v).'"';
            }
            $renderedLabels = '{'.implode(',', $pairs).'}';
        }

        return $metricName.$renderedLabels.' '.$value."\n";
    }

    private function escapeLabelValue(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '"' => '\\"',
            "\n" => '\\n',
        ]);
    }
}
