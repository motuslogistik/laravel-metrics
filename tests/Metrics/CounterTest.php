<?php

it('increments by 1 by default on incr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr();
    $c->incr();
    $c->incr();

    $point = $this->dataPoint($this->metric('orders_created'), ['status' => 'paid']);

    expect($point->value)->toBe(3);
});

it('increments by a custom amount on incr()', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->incr(5);
    $c->incr(2);

    expect($this->dataPoint($this->metric('orders_created'), ['status' => 'paid'])->value)->toBe(7);
});

it('decrements by 1 by default on decr()', function () {
    $c = counter('queue_depth');
    $c->incr(10);
    $c->decr();
    $c->decr();

    expect($this->dataPoint($this->metric('queue_depth'))->value)->toBe(8);
});

it('decrements by a custom amount on decr()', function () {
    $c = counter('queue_depth');
    $c->incr(10);
    $c->decr(3);

    expect($this->dataPoint($this->metric('queue_depth'))->value)->toBe(7);
});

it('treats record() as an alias for incr() with no amount', function () {
    $c = counter('orders_created')->label('status', 'paid');
    $c->record();
    $c->record();

    expect($this->dataPoint($this->metric('orders_created'), ['status' => 'paid'])->value)->toBe(2);
});

it('accepts labels as an array in the helper', function () {
    counter('orders_created', ['status' => 'paid', 'channel' => 'web'])->incr();

    $point = $this->dataPoint($this->metric('orders_created'), ['status' => 'paid', 'channel' => 'web']);

    expect($point->value)->toBe(1);
});

it('merges helper labels with subsequent label() calls', function () {
    counter('orders_created', ['status' => 'paid'])
        ->label('channel', 'web')
        ->incr();

    $point = $this->dataPoint($this->metric('orders_created'), ['status' => 'paid', 'channel' => 'web']);

    expect($point->value)->toBe(1);
});
