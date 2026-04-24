<?php

use motuslogistik\Metrics\Stores\SwooleTableStore;

beforeEach(function () {
    if (! extension_loaded('swoole') || ! class_exists(\Swoole\Table::class)) {
        $this->markTestSkipped('swoole extension is not available');
    }

    $this->store = new SwooleTableStore(size: 256);
});

it('returns false for missing keys', function () {
    expect($this->store->has('missing'))->toBeFalse()
        ->and($this->store->get('missing'))->toBeFalse();
});

it('stores numeric values in the value table', function () {
    $this->store->set('foo', 42);

    expect($this->store->has('foo'))->toBeTrue()
        ->and($this->store->get('foo'))->toBe(42.0);
});

it('stores string values in the string table', function () {
    $this->store->set('type:orders:created', 'counter');

    expect($this->store->get('type:orders:created'))->toBe('counter');
});

it('increments atomically from zero', function () {
    $this->store->incr('counter');
    $this->store->incr('counter');
    $this->store->incr('counter');

    expect($this->store->get('counter'))->toBe(3.0);
});

it('decrements from a set value', function () {
    $this->store->set('counter', 5);
    $this->store->decr('counter');
    $this->store->decr('counter');

    expect($this->store->get('counter'))->toBe(3.0);
});

it('clears a key from both tables', function () {
    $this->store->set('num', 1);
    $this->store->set('str', 'x');

    $this->store->clear('num');
    $this->store->clear('str');

    expect($this->store->has('num'))->toBeFalse()
        ->and($this->store->has('str'))->toBeFalse();
});

it('iterates across both tables filtered by prefix', function () {
    $this->store->set('metrics|a', 1);
    $this->store->set('metrics|b', 2);
    $this->store->set('metrics|__types|a', 'counter');
    $this->store->set('other|z', 99);

    $collected = [];
    foreach ($this->store->iterator('metrics|') as $k => $v) {
        $collected[$k] = $v;
    }

    expect($collected)->toHaveKeys(['metrics|a', 'metrics|b', 'metrics|__types|a'])
        ->and($collected)->not->toHaveKey('other|z');
});
