<?php

it('records a value on record()', function () {
    gauge('cpu_usage')->label('host', 'web1')->record(0.83);

    expect($this->dataPoint($this->metric('cpu_usage'), ['host' => 'web1'])->value)->toBe(0.83);
});

it('keeps the last value on subsequent record()', function () {
    $g = gauge('cpu_usage')->label('host', 'web1');
    $g->record(0.5);
    $g->record(0.9);

    expect($this->dataPoint($this->metric('cpu_usage'), ['host' => 'web1'])->value)->toBe(0.9);
});

it('emits a Gauge data type', function () {
    gauge('cpu_usage')->record(1);

    expect($this->metric('cpu_usage')->data)
        ->toBeInstanceOf(\OpenTelemetry\SDK\Metrics\Data\Gauge::class);
});

it('treats set() as an alias for record()', function () {
    gauge('orders_total')->set(42);

    expect($this->dataPoint($this->metric('orders_total'))->value)->toBe(42);
});
