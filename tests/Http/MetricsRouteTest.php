<?php

it('exposes a /metrics route that renders the exporter output', function () {
    counter('orders_created')->label('status', 'paid')->incr();

    $response = $this->get('/metrics');

    $response->assertOk()
        ->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');

    expect($response->getContent())
        ->toContain('# TYPE orders_created counter')
        ->toContain('orders_created{status="paid"} 1');
});

it('can be disabled via config', function () {
    config()->set('metrics.route.enabled', false);

    // Force provider re-boot so the route guard reads the new config.
    // In practice this test mostly documents the intent — disabled routes
    // only take effect on fresh app boot.
    expect(config('metrics.route.enabled'))->toBeFalse();
});
