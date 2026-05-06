<?php

use motuslogistik\Metrics\Contracts\Store;

it('sets the store to a fixed value on set()', function () {
    counter('orders_created')->label('status', 'paid')->set(7);

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(7);
});

it('overwrites the previous value on subsequent set() calls', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->set(3);
    $c->set(10);

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(10);
});

it('treats record() as an alias for incr() with no amount', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->record();
    $c->record();

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(2);
});

it('increments by 1 by default on incr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr();
    $c->incr();
    $c->incr();

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(3);
});

it('increments by a custom amount on incr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr(5);
    $c->incr(2);

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(7);
});

it('decrements by 1 by default on decr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr(10);
    $c->decr();
    $c->decr();

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(8);
});

it('decrements by a custom amount on decr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr(10);
    $c->decr(3);

    expect(app(Store::class)->get('metrics|orders_created;status=paid'))->toBe(7);
});

it('registers its type in the sidecar registry on set()', function () {
    counter('orders_created')->set(1);

    expect(app(Store::class)->get('metrics|__types|orders_created'))->toBe('counter');
});

it('registers its type in the sidecar registry on incr()', function () {
    counter('orders_created')->incr();

    expect(app(Store::class)->get('metrics|__types|orders_created'))->toBe('counter');
});

it('registers its type in the sidecar registry on decr()', function () {
    counter('orders_created')->decr();

    expect(app(Store::class)->get('metrics|__types|orders_created'))->toBe('counter');
});

it('accepts labels as an array in the helper', function () {
    counter('orders_created', ['status' => 'paid', 'channel' => 'web'])->incr();

    expect(app(Store::class)->get('metrics|orders_created;channel=web;status=paid'))->toBe(1);
});

it('merges helper labels with subsequent label() calls', function () {
    counter('orders_created', ['status' => 'paid'])
        ->label('channel', 'web')
        ->incr();

    expect(app(Store::class)->get('metrics|orders_created;channel=web;status=paid'))->toBe(1);
});
