<?php

use motuslogistik\Metrics\Metrics\Counter;
use motuslogistik\Metrics\Metrics\Gauge;
use motuslogistik\Metrics\PendingMetric;

enum TestName: string
{
    case OrdersCreated = 'orders_created';
}

enum TestLabelKey: string
{
    case Status = 'status';
}

enum TestLabelValue: string
{
    case Paid = 'paid';
}

enum TestIntValue: int
{
    case One = 1;
}

it('returns a Counter from counter() with labels carried over', function () {
    metric('orders_created')
        ->label('status', 'paid')
        ->counter()
        ->incr();

    expect($this->dataPoint($this->metric('orders_created'), ['status' => 'paid'])->value)->toBe(1);
});

it('returns a Gauge from gauge() with labels carried over', function () {
    metric('cpu_usage')
        ->label('host', 'web1')
        ->gauge()
        ->record(0.5);

    expect($this->dataPoint($this->metric('cpu_usage'), ['host' => 'web1'])->value)->toBe(0.5);
});

it('returns a Counter instance from ->counter()', function () {
    expect((new PendingMetric('x'))->counter())->toBeInstanceOf(Counter::class);
});

it('returns a Gauge instance from ->gauge()', function () {
    expect((new PendingMetric('x'))->gauge())->toBeInstanceOf(Gauge::class);
});

it('overwrites a label when set twice with the same name', function () {
    counter('orders_created')
        ->label('status', 'pending')
        ->label('status', 'paid')
        ->incr();

    $metric = $this->metric('orders_created');

    expect($this->dataPoint($metric, ['status' => 'paid'])->value)->toBe(1)
        ->and($this->dataPoint($metric, ['status' => 'pending']))->toBeNull();
});

it('accepts a backed enum for the name', function () {
    counter(TestName::OrdersCreated)->incr();

    expect($this->metric('orders_created'))->not->toBeNull();
});

it('accepts backed enums for label name and value', function () {
    counter('orders_created')
        ->label(TestLabelKey::Status, TestLabelValue::Paid)
        ->incr();

    expect($this->dataPoint($this->metric('orders_created'), ['status' => 'paid'])->value)->toBe(1);
});

it('coerces int-backed enum values to strings', function () {
    counter('orders_created')
        ->label('count', TestIntValue::One)
        ->incr();

    expect($this->dataPoint($this->metric('orders_created'), ['count' => '1'])->value)->toBe(1);
});

it('treats ->global() as a no-op for backward compatibility', function () {
    counter('users_total')->global()->incr();

    expect($this->dataPoint($this->metric('users_total'))->value)->toBe(1);
});

it('propagates ->global() through metric type conversions without error', function () {
    metric('users_total')->global()->counter()->incr();

    expect($this->dataPoint($this->metric('users_total'))->value)->toBe(1);
});
