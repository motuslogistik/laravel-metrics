<?php

declare(strict_types=1);

namespace motuslogistik\Metrics;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\Event;
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

        if (config('metrics.flush_on_octane', true)) {
            // Long-lived Octane workers never reach process-death flush, so push
            // buffered metrics whenever a unit of work ends (after the response is
            // sent, so no user-facing latency) and on worker shutdown. Listening on
            // string class names keeps this zero-config — harmless when Octane isn't
            // installed, since these events are then never dispatched.
            foreach ([
                'Laravel\Octane\Events\RequestTerminated',
                'Laravel\Octane\Events\TaskTerminated',
                'Laravel\Octane\Events\TickTerminated',
                'Laravel\Octane\Events\WorkerStopping',
            ] as $event) {
                Event::listen($event, fn () => Metrics::flush());
            }
        }
    }
}
