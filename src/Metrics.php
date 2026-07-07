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
        return self::meter()->createCounter(self::prefixed($name));
    }

    public static function gauge(string $name): GaugeInterface
    {
        return self::meter()->createGauge(self::prefixed($name));
    }

    public static function histogram(string $name): HistogramInterface
    {
        // Bucket overrides are keyed by the logical (unprefixed) name, so the
        // lookup happens before the prefix is applied.
        $buckets = config('metrics.histogram_buckets.'.$name)
            ?? config('metrics.default_histogram_buckets');

        $advisory = $buckets !== null ? ['ExplicitBucketBoundaries' => $buckets] : [];

        return self::meter()->createHistogram(self::prefixed($name), advisory: $advisory);
    }

    /**
     * Prepend the configured name prefix. The prefix is baked into the OTel
     * instrument identity (and cached per process), so it must stay stable
     * across deploys — see `config/metrics.php`.
     */
    protected static function prefixed(string $name): string
    {
        $prefix = (string) config('metrics.prefix', '');

        return $prefix === '' ? $name : $prefix.$name;
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
