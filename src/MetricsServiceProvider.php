<?php

declare(strict_types=1);

namespace motuslogistik\Metrics;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
use OpenTelemetry\API\Globals;
use OpenTelemetry\SDK\Metrics\MeterProviderInterface;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('metrics')
            ->hasConfigFile();
    }

    public function packageBooted(): void
    {
        if (config('metrics.flush_on_queue_job', true)) {
            Queue::after(fn (JobProcessed $event) => $this->flushMetrics());
            Queue::failing(fn (JobFailed $event) => $this->flushMetrics());
        }
    }

    /**
     * Force-flush OTel metrics. The OTel PHP SDK uses an ExportingReader with
     * no periodic export — in long-running queue workers, metrics would
     * otherwise only flush when the worker process dies.
     *
     * forceFlush() lives on the SDK MeterProviderInterface, not the API one,
     * so we narrow the type. Noop providers (e.g. OTEL_SDK_DISABLED) fall
     * through silently.
     */
    protected function flushMetrics(): void
    {
        $provider = Globals::meterProvider();
        if ($provider instanceof MeterProviderInterface) {
            $provider->forceFlush();
        }
    }
}
