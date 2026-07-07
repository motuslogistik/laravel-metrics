<?php

use OpenTelemetry\SDK\Metrics\Data\Histogram as HistogramData;

it('prepends the configured prefix to counter names', function () {
    config()->set('metrics.prefix', 'motus_');

    counter('orders_created')->incr();

    expect($this->metric('motus_orders_created'))->not->toBeNull()
        ->and($this->metric('orders_created'))->toBeNull();
});

it('prepends the configured prefix to gauge names', function () {
    config()->set('metrics.prefix', 'motus_');

    gauge('queue_depth')->set(7);

    expect($this->metric('motus_queue_depth'))->not->toBeNull();
});

it('prepends the configured prefix to histogram names', function () {
    config()->set('metrics.prefix', 'motus_');

    histogram('request_seconds')->record(0.5);

    expect($this->metric('motus_request_seconds'))->not->toBeNull();
});

it('leaves names untouched when the prefix is empty (default)', function () {
    counter('orders_created')->incr();

    expect($this->metric('orders_created'))->not->toBeNull();
});

it('raw-concatenates the prefix without inserting a separator', function () {
    config()->set('metrics.prefix', 'motus.');

    counter('orders_created')->incr();

    expect($this->metric('motus.orders_created'))->not->toBeNull();
});

it('keys histogram bucket overrides on the unprefixed logical name', function () {
    config()->set('metrics.prefix', 'motus_');
    config()->set('metrics.histogram_buckets.request_seconds', [0.1, 0.2, 0.3]);

    histogram('request_seconds')->record(0.15);

    $data = $this->metric('motus_request_seconds')->data;

    expect($data)->toBeInstanceOf(HistogramData::class)
        ->and($data->dataPoints[0]->explicitBounds)->toBe([0.1, 0.2, 0.3]);
});
