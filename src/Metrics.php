<?php

namespace motuslogistik\Metrics;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface as SdkMeterProviderInterface;

class Metrics
{
    public static function meter(): MeterInterface
    {
        return self::meterProvider()->getMeter(
            config('metrics.meter_name', 'motuslogistik/metrics'),
        );
    }

    public static function counter(string $name): CounterInterface
    {
        return self::meter()->createCounter($name);
    }

    public static function gauge(string $name): GaugeInterface
    {
        return self::meter()->createGauge($name);
    }

    public static function histogram(string $name): HistogramInterface
    {
        $buckets = config('metrics.histogram_buckets.'.$name)
            ?? config('metrics.default_histogram_buckets');

        $advisory = $buckets !== null ? ['ExplicitBucketBoundaries' => $buckets] : [];

        return self::meter()->createHistogram($name, advisory: $advisory);
    }

    /**
     * Force-flush the OTel MeterProvider. The PHP SDK uses an ExportingReader
     * with no periodic export — in long-running processes (queue workers, AMQP
     * consumers, daemons) metrics would otherwise only flush on process death.
     *
     * `forceFlush()` lives on the SDK MeterProviderInterface, not the API one,
     * so a noop provider (e.g. when `OTEL_SDK_DISABLED=true`) falls through
     * silently.
     */
    public static function flush(): void
    {
        $provider = self::meterProvider();
        if ($provider instanceof SdkMeterProviderInterface) {
            $provider->forceFlush();
        }
    }

    protected static function meterProvider(): MeterProviderInterface
    {
        if (app()->bound(MeterProviderInterface::class)) {
            return app(MeterProviderInterface::class);
        }

        return Globals::meterProvider();
    }
}
