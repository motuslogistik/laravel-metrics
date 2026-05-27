<?php

use OpenTelemetry\SDK\Metrics\Data\Histogram as HistogramData;

it('records a value on record()', function () {
    histogram('http_latency')->label('path', '/home')->record(123);

    $point = $this->dataPoint($this->metric('http_latency'), ['path' => '/home']);

    expect($point->sum)->toBe(123)
        ->and($point->count)->toBe(1);
});

it('aggregates multiple observations', function () {
    $h = histogram('http_latency')->label('path', '/home');
    $h->record(10);
    $h->record(20);
    $h->record(30);

    $point = $this->dataPoint($this->metric('http_latency'), ['path' => '/home']);

    expect($point->count)->toBe(3)
        ->and($point->sum)->toBe(60);
});

it('emits a Histogram data type', function () {
    histogram('http_latency')->record(1);

    expect($this->metric('http_latency')->data)->toBeInstanceOf(HistogramData::class);
});

it('returns the closure result from time()', function () {
    $result = histogram('http_render')->time(fn () => 'payload');

    expect($result)->toBe('payload');
});

it('records the duration in seconds as a float after time()', function () {
    histogram('http_render')->label('path', '/home')->time(fn () => null);

    $point = $this->dataPoint($this->metric('http_render'), ['path' => '/home']);

    expect($point->sum)->toBeFloat()->toBeGreaterThanOrEqual(0);
});

it('accepts labels as an array in the helper', function () {
    histogram('http_latency', ['path' => '/home'])->record(50);

    expect($this->dataPoint($this->metric('http_latency'), ['path' => '/home'])->sum)->toBe(50);
});

it('returns a Histogram via metric()->histogram() with labels carried over', function () {
    metric('http_latency')
        ->label('path', '/home')
        ->histogram()
        ->record(42);

    expect($this->dataPoint($this->metric('http_latency'), ['path' => '/home'])->sum)->toBe(42);
});
