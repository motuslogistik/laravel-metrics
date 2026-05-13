<?php

use motuslogistik\Metrics\Contracts\Store;

it('returns the closure result from observe()', function () {
    $result = gauge('http_latency')->observe(fn () => 'payload');

    expect($result)->toBe('payload');
});

it('invokes the observe closure exactly once', function () {
    $calls = 0;
    gauge('http_latency')->observe(function () use (&$calls) {
        $calls++;
    });

    expect($calls)->toBe(1);
});

it('stores the duration in seconds as a float after observe()', function () {
    gauge('http_latency')->label('path', 'home')->observe(fn () => null);

    expect(app(Store::class)->get('metrics|http_latency;path=home'))
        ->toBeFloat()
        ->toBeGreaterThanOrEqual(0);
});

it('registers the gauge type after observe()', function () {
    gauge('http_latency')->observe(fn () => null);

    expect(app(Store::class)->get('metrics|__types|http_latency'))->toBe('gauge');
});
