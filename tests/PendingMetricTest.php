<?php

use motuslogistik\Metrics\Metrics\Counter;
use motuslogistik\Metrics\Metrics\Gauge;
use motuslogistik\Metrics\Metrics\Histogram;
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

function invokeGetKey(PendingMetric $metric): string
{
    $method = new ReflectionMethod($metric, 'getKey');
    $method->setAccessible(true);

    return $method->invoke($metric);
}

it('builds a key from the name when no labels are set', function () {
    $metric = new PendingMetric('orders_created');

    expect(invokeGetKey($metric))->toBe('metrics|orders_created');
});

it('appends a single label to the key', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label('status', 'paid');

    expect(invokeGetKey($metric))->toBe('metrics|orders_created;status=paid');
});

it('sorts labels alphabetically by key', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label('status', 'paid')
        ->label('country', 'se')
        ->label('channel', 'web');

    expect(invokeGetKey($metric))
        ->toBe('metrics|orders_created;channel=web;country=se;status=paid');
});

it('overwrites a label when set twice with the same name', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label('status', 'pending')
        ->label('status', 'paid');

    expect(invokeGetKey($metric))->toBe('metrics|orders_created;status=paid');
});

it('produces the same key regardless of label insertion order', function () {
    $a = (new PendingMetric('orders_created'))
        ->label('a', '1')
        ->label('b', '2');

    $b = (new PendingMetric('orders_created'))
        ->label('b', '2')
        ->label('a', '1');

    expect(invokeGetKey($a))->toBe(invokeGetKey($b));
});

it('rejects reserved characters in the name', function (string $name) {
    new PendingMetric($name);
})->with([
    'pipe' => ['crea|ted'],
    'semicolon' => ['crea;ted'],
    'equals' => ['crea=ted'],
])->throws(InvalidArgumentException::class);

it('accepts colons in the name', function () {
    $metric = new PendingMetric('orders:created');

    expect(invokeGetKey($metric))->toBe('metrics|orders:created');
});

it('rejects reserved characters in a label name', function (string $labelName) {
    (new PendingMetric('orders_created'))->label($labelName, 'paid');
})->with([
    'pipe' => ['sta|tus'],
    'semicolon' => ['sta;tus'],
    'equals' => ['sta=tus'],
])->throws(InvalidArgumentException::class);

it('rejects reserved characters in a label value', function (string $labelValue) {
    (new PendingMetric('orders_created'))->label('status', $labelValue);
})->with([
    'pipe' => ['pa|id'],
    'semicolon' => ['pa;id'],
    'equals' => ['pa=id'],
])->throws(InvalidArgumentException::class);

it('returns a Counter from counter() with labels carried over', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label('status', 'paid')
        ->counter();

    expect($metric)->toBeInstanceOf(Counter::class)
        ->and(invokeGetKey($metric))->toBe('metrics|orders_created;status=paid');
});

it('returns a Gauge from gauge() with labels carried over', function () {
    $metric = (new PendingMetric('cpu_usage'))
        ->label('host', 'web1')
        ->gauge();

    expect($metric)->toBeInstanceOf(Gauge::class)
        ->and(invokeGetKey($metric))->toBe('metrics|cpu_usage;host=web1');
});

it('returns a Histogram from histogram() with labels carried over', function () {
    $metric = (new PendingMetric('http_latency'))
        ->label('path', 'home')
        ->histogram();

    expect($metric)->toBeInstanceOf(Histogram::class)
        ->and(invokeGetKey($metric))->toBe('metrics|http_latency;path=home');
});

it('accepts a backed enum for the name', function () {
    $metric = new PendingMetric(TestName::OrdersCreated);

    expect($metric->name)->toBe('orders_created')
        ->and(invokeGetKey($metric))->toBe('metrics|orders_created');
});

it('accepts backed enums for label name and value', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label(TestLabelKey::Status, TestLabelValue::Paid);

    expect(invokeGetKey($metric))->toBe('metrics|orders_created;status=paid');
});

it('coerces int-backed enum values to strings', function () {
    $metric = (new PendingMetric('orders_created'))
        ->label('count', TestIntValue::One);

    expect(invokeGetKey($metric))->toBe('metrics|orders_created;count=1');
});
