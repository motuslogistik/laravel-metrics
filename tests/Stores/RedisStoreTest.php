<?php

use Illuminate\Support\Facades\Redis;
use motuslogistik\Metrics\Stores\RedisStore;

beforeEach(function () {
    config()->set('database.redis.default', [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => (int) env('REDIS_PORT', 6379),
        'database' => (int) env('REDIS_DB', 15),
    ]);

    try {
        Redis::connection()->ping();
    } catch (Throwable $e) {
        $this->markTestSkipped('redis server not available: '.$e->getMessage());
    }

    Redis::connection()->flushdb();

    $this->store = new RedisStore;
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

    expect((int) $this->store->get('counter'))->toBe(3);
});

it('increments by float using incrbyfloat', function () {
    $this->store->incr('latency', 1.5);
    $this->store->incr('latency', 2.25);

    expect((float) $this->store->get('latency'))->toBe(3.75);
});

it('decrements an existing counter', function () {
    $this->store->set('counter', 5);
    $this->store->decr('counter');
    $this->store->decr('counter');

    expect((int) $this->store->get('counter'))->toBe(3);
});

it('clears a key', function () {
    $this->store->set('foo', 'bar');
    $this->store->clear('foo');

    expect($this->store->has('foo'))->toBeFalse();
});

it('iterates keys filtered by prefix', function () {
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
