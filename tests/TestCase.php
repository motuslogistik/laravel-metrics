<?php

namespace motuslogistik\Metrics\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use motuslogistik\Metrics\MetricsServiceProvider;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\Data\Metric;
use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricExporter\InMemoryExporter;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected InMemoryExporter $exporter;

    protected ExportingReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'motuslogistik\\Metrics\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->exporter = new InMemoryExporter;
        $this->reader = new ExportingReader($this->exporter);
        $meterProvider = MeterProvider::builder()
            ->addReader($this->reader)
            ->build();

        $this->app->instance(MeterProviderInterface::class, $meterProvider);
    }

    /**
     * Force collection and return all metrics emitted since the last call.
     *
     * @return array<int, Metric>
     */
    protected function collectMetrics(): array
    {
        $this->reader->collect();

        return $this->exporter->collect(reset: true);
    }

    /**
     * Find the first metric with the given name in the most recent collection.
     */
    protected function metric(string $name): ?Metric
    {
        foreach ($this->collectMetrics() as $metric) {
            if ($metric->name === $name) {
                return $metric;
            }
        }

        return null;
    }

    /**
     * Find a data point on a metric matching the given attributes (subset match).
     *
     * @param  array<string, string|int|float|bool>  $attributes
     */
    protected function dataPoint(Metric $metric, array $attributes = []): mixed
    {
        foreach ($metric->data->dataPoints as $point) {
            $pointAttrs = $point->attributes->toArray();
            $matches = true;
            foreach ($attributes as $k => $v) {
                if (($pointAttrs[$k] ?? null) !== $v) {
                    $matches = false;
                    break;
                }
            }
            if ($matches) {
                return $point;
            }
        }

        return null;
    }

    protected function getPackageProviders($app)
    {
        return [
            MetricsServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
    }
}
