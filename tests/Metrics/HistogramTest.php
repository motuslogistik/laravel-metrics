<?php

use motuslogistik\Metrics\Contracts\Store;

it('stores the value on record()', function () {
    histogram('http_latency')->label('path', 'home')->record(250);

    expect(app(Store::class)->get('metrics|http_latency;path=home'))->toBe(250);
});

it('overwrites the previous value on subsequent record()', function () {
    $h = histogram('http_latency')->label('path', 'home');
    $h->record(100);
    $h->record(250);

    expect(app(Store::class)->get('metrics|http_latency;path=home'))->toBe(250);
});

it('registers its type in the sidecar registry', function () {
    histogram('http_latency')->record(10);

    expect(app(Store::class)->get('metrics|__types|http_latency'))->toBe('histogram');
});

it('returns the closure result from observe()', function () {
    $result = histogram('http_latency')->observe(fn () => 'payload');

    expect($result)->toBe('payload');
});

it('invokes the observe closure exactly once', function () {
    $calls = 0;
    histogram('http_latency')->observe(function () use (&$calls) {
        $calls++;
    });

    expect($calls)->toBe(1);
});

it('stores a numeric duration at the key after observe()', function () {
    histogram('http_latency')->label('path', 'home')->observe(fn () => null);

    expect(app(Store::class)->get('metrics|http_latency;path=home'))
        ->toBeNumeric()
        ->toBeGreaterThanOrEqual(0);
});
