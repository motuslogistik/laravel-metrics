<?php

use OpenTelemetry\API\Trace\StatusCode;

enum SpanTestResource: string
{
    case Orders = 'orders';
}

it('returns the closure result from time()', function () {
    $result = span('jsonapi.build_response')->time(fn () => 'payload');

    expect($result)->toBe('payload');
});

it('exports a span with the given name', function () {
    span('jsonapi.build_response')->time(fn () => null);

    expect($this->span('jsonapi.build_response'))->not->toBeNull();
});

it('does not prefix span names even when a metric prefix is configured', function () {
    config()->set('metrics.prefix', 'motus_');

    span('jsonapi.build_response')->time(fn () => null);

    expect($this->span('jsonapi.build_response'))->not->toBeNull()
        ->and($this->span('motus_jsonapi.build_response'))->toBeNull();
});

it('sets attributes on the exported span', function () {
    span('jsonapi.handle_operation')
        ->attribute('jsonapi.resource_type', 'orders')
        ->attribute('jsonapi.operation', 'add')
        ->time(fn () => null);

    $attributes = $this->span('jsonapi.handle_operation')->getAttributes()->toArray();

    expect($attributes)->toMatchArray([
        'jsonapi.resource_type' => 'orders',
        'jsonapi.operation' => 'add',
    ]);
});

it('normalizes backed enum attribute names and values', function () {
    span('jsonapi.handle_operation')
        ->attribute(SpanTestResource::Orders, SpanTestResource::Orders)
        ->time(fn () => null);

    expect($this->span('jsonapi.handle_operation')->getAttributes()->toArray())
        ->toMatchArray(['orders' => 'orders']);
});

it('ends the span and records the exception when the closure throws', function () {
    expect(fn () => span('jsonapi.handle_operation')->time(
        fn () => throw new RuntimeException('boom'),
    ))->toThrow(RuntimeException::class, 'boom');

    $span = $this->span('jsonapi.handle_operation');

    expect($span)->not->toBeNull()
        ->and($span->hasEnded())->toBeTrue()
        ->and($span->getStatus()->getCode())->toBe(StatusCode::STATUS_ERROR);

    $events = $span->getEvents();

    expect($events)->toHaveCount(1)
        ->and($events[0]->getName())->toBe('exception')
        ->and($events[0]->getAttributes()->get('exception.message'))->toBe('boom');
});

it('activates the span so nested spans become children', function () {
    span('outer')->time(fn () => span('inner')->time(fn () => null));

    $outer = $this->span('outer');
    $inner = $this->span('inner');

    expect($inner->getParentSpanId())->toBe($outer->getSpanId());
});
