<?php

namespace motuslogistik\Metrics;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Metrics\CounterInterface;
use OpenTelemetry\API\Metrics\GaugeInterface;
use OpenTelemetry\API\Metrics\HistogramInterface;
use OpenTelemetry\API\Metrics\MeterInterface;
use OpenTelemetry\API\Metrics\MeterProviderInterface;

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
        return self::meter()->createHistogram($name);
    }

    protected static function meterProvider(): MeterProviderInterface
    {
        if (app()->bound(MeterProviderInterface::class)) {
            return app(MeterProviderInterface::class);
        }

        return Globals::meterProvider();
    }
}
