<?php

use motuslogistik\Metrics\Contracts\Store;

it('increments the store on record()', function () {
    counter('orders_created')->label('status', 'paid')->record();

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(1);
});

it('increments cumulatively across multiple record() calls', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->record();
    $c->record();
    $c->record();

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(3);
});

it('registers its type in the sidecar registry', function () {
    counter('orders_created')->record();

    expect(app(Store::class)->get('metrics|__types|orders_created'))->toBe('counter');
});

it('accepts labels as an array in the helper', function () {
    counter('orders_created', ['status' => 'paid', 'channel' => 'web'])->record();

    expect(app(Store::class)->get('metrics|orders_created;channel=web;status=paid'))->toBe(1);
});

it('merges helper labels with subsequent label() calls', function () {
    counter('orders_created', ['status' => 'paid'])
        ->label('channel', 'web')
        ->record();

    expect(app(Store::class)->get('metrics|orders_created;channel=web;status=paid'))->toBe(1);
});
