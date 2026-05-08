<?php

use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Exporters\PrometheusExporter;
use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\Stores\ArrayStore;

function exporterRender(): string
{
    return (new PrometheusExporter(app(Store::class)))->render();
}

it('renders a counter with TYPE line and single series', function () {
    counter('orders_created')->label('status', 'paid')->incr();

    expect(exporterRender())->toContain(
        "# TYPE orders_created counter\n",
        'orders_created{status="paid"} 1',
    );
});

it('renders a gauge with its set value', function () {
    gauge('cpu_usage')->label('host', 'web1')->record(0.83);

    $out = exporterRender();

    expect($out)->toContain('# TYPE cpu_usage gauge')
        ->and($out)->toContain('cpu_usage{host="web1"} 0.83');
});

it('renders multiple series for the same metric', function () {
    counter('orders_created')->label('status', 'paid')->incr();
    counter('orders_created')->label('status', 'paid')->incr();
    counter('orders_created')->label('status', 'failed')->incr();

    $out = exporterRender();

    expect($out)->toContain('orders_created{status="paid"} 2')
        ->and($out)->toContain('orders_created{status="failed"} 1');
});

it('renders a metric with no labels', function () {
    counter('system_boot')->incr();

    expect(exporterRender())->toContain("system_boot 1\n");
});

it('omits the __types sidecar keys from output', function () {
    counter('orders_created')->incr();

    expect(exporterRender())->not->toContain('__types');
});

it('escapes quotes and backslashes in label values', function () {
    counter('events_logged')->label('message', 'he said "hi"')->incr();

    expect(exporterRender())->toContain('events_logged{message="he said \\"hi\\""} 1');
});

it('merges series and types from multiple stores', function () {
    $local = new ArrayStore;
    $global = new ArrayStore;

    $this->app->instance(Store::class, $local);
    $this->app->instance(Metrics::GLOBAL_STORE, $global);

    counter('orders_created')->label('status', 'paid')->incr();
    counter('users_total')->global()->incr();

    $out = (new PrometheusExporter([$local, $global]))->render();

    expect($out)
        ->toContain('# TYPE orders_created counter')
        ->and($out)->toContain('orders_created{status="paid"} 1')
        ->and($out)->toContain('# TYPE users_total counter')
        ->and($out)->toContain("users_total 1\n");
});

it('concatenates samples when the same metric exists in both stores', function () {
    $local = new ArrayStore;
    $global = new ArrayStore;

    $this->app->instance(Store::class, $local);
    $this->app->instance(Metrics::GLOBAL_STORE, $global);

    counter('orders_created')->label('status', 'paid')->incr();
    counter('orders_created')->label('status', 'failed')->global()->incr();

    $out = (new PrometheusExporter([$local, $global]))->render();

    expect(substr_count($out, '# TYPE orders_created counter'))->toBe(1)
        ->and($out)->toContain('orders_created{status="paid"} 1')
        ->and($out)->toContain('orders_created{status="failed"} 1');
});
