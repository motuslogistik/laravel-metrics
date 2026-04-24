<?php

use motuslogistik\Metrics\Contracts\Store;
use motuslogistik\Metrics\Exporters\PrometheusExporter;

function exporterRender(): string
{
    return (new PrometheusExporter(app(Store::class)))->render();
}

it('renders a counter with TYPE line and single series', function () {
    counter('orders_created')->label('status', 'paid')->record();

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
    counter('orders_created')->label('status', 'paid')->record();
    counter('orders_created')->label('status', 'paid')->record();
    counter('orders_created')->label('status', 'failed')->record();

    $out = exporterRender();

    expect($out)->toContain('orders_created{status="paid"} 2')
        ->and($out)->toContain('orders_created{status="failed"} 1');
});

it('renders a metric with no labels', function () {
    counter('system_boot')->record();

    expect(exporterRender())->toContain("system_boot 1\n");
});

it('omits the __types sidecar keys from output', function () {
    counter('orders_created')->record();

    expect(exporterRender())->not->toContain('__types');
});

it('escapes quotes and backslashes in label values', function () {
    counter('events_logged')->label('message', 'he said "hi"')->record();

    expect(exporterRender())->toContain('events_logged{message="he said \\"hi\\""} 1');
});
