<?php

use motuslogistik\Metrics\Stores\APCStore;

beforeEach(function () {
    if (! function_exists('apcu_enabled') || ! apcu_enabled()) {
        $this->markTestSkipped('apcu is not available / not enabled for CLI');
    }

    apcu_clear_cache();

    $this->store = new APCStore;
});

it('returns false for missing keys', function () {
    expect($this->store->has('missing'))->toBeFalse()
        ->and($this->store->get('missing'))->toBeFalse();
});

it('sets and gets a value', function () {
    $this->store->set('foo', 'bar');

    expect($this->store->has('foo'))->toBeTrue()
        ->and($this->store->get('foo'))->toBe('bar');
});

it('increments from nothing to one and then cumulatively', function () {
    $this->store->incr('counter');
    $this->store->incr('counter');
    $this->store->incr('counter');

    expect($this->store->get('counter'))->toBe(3);
});

it('decrements an existing counter', function () {
    $this->store->set('counter', 5);
    $this->store->decr('counter');
    $this->store->decr('counter');

    expect($this->store->get('counter'))->toBe(3);
});

it('clears a key', function () {
    $this->store->set('foo', 'bar');
    $this->store->clear('foo');

    expect($this->store->has('foo'))->toBeFalse();
});
