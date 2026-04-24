<?php

use motuslogistik\Metrics\Contracts\Store;

it('sets the value on record()', function () {
    gauge('cpu_usage')->label('host', 'web1')->record(0.83);

    expect(app(Store::class)->get('metrics|cpu_usage;host=web1'))->toBe(0.83);
});

it('overwrites the previous value on subsequent record()', function () {
    $g = gauge('cpu_usage')->label('host', 'web1');
    $g->record(0.5);
    $g->record(0.9);

    expect(app(Store::class)->get('metrics|cpu_usage;host=web1'))->toBe(0.9);
});

it('registers its type in the sidecar registry', function () {
    gauge('cpu_usage')->record(1);

    expect(app(Store::class)->get('metrics|__types|cpu_usage'))->toBe('gauge');
});
