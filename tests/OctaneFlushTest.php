<?php

use Illuminate\Support\Facades\Event;
use OpenTelemetry\SDK\Metrics\Data\Metric;

it('registers flush listeners on Octane lifecycle events by default', function () {
    foreach ([
        'Laravel\Octane\Events\RequestTerminated',
        'Laravel\Octane\Events\TaskTerminated',
        'Laravel\Octane\Events\TickTerminated',
        'Laravel\Octane\Events\WorkerStopping',
    ] as $event) {
        expect(Event::hasListeners($event))->toBeTrue();
    }
});

it('flushes buffered metrics when an Octane request terminates', function () {
    histogram('octane_latency')->record(0.5);

    // The SDK's ExportingReader buffers — nothing reaches the exporter yet.
    expect($this->exporter->collect())->toBeEmpty();

    event('Laravel\Octane\Events\RequestTerminated');

    $names = array_map(fn (Metric $m) => $m->name, $this->exporter->collect());
    expect($names)->toContain('octane_latency');
});

it('flushes buffered metrics when an Octane worker stops', function () {
    histogram('octane_shutdown_latency')->record(0.25);

    event('Laravel\Octane\Events\WorkerStopping');

    $names = array_map(fn (Metric $m) => $m->name, $this->exporter->collect());
    expect($names)->toContain('octane_shutdown_latency');
});
