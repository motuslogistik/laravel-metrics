<?php

declare(strict_types=1);

namespace motuslogistik\Metrics;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Queue;
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
            Queue::after(fn (JobProcessed $event) => Metrics::flush());
            Queue::failing(fn (JobFailed $event) => Metrics::flush());
        }
    }
}
