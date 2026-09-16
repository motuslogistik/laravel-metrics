<?php

namespace motuslogistik\Metrics\Tracing;

use BackedEnum;
use Closure;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use Throwable;

/**
 * A single OTel tracing span around a closure.
 *
 * Deliberately not a PendingMetric: a span is not a metric, and inheriting
 * label()/counter()/gauge()/histogram() would offer an API that cannot work
 * here. The small normalize() duplication is the price.
 */
class Span
{
    public readonly string $name;

    /** @var array<string, string> */
    protected array $attributes = [];

    public function __construct(string|BackedEnum $name)
    {
        $this->name = $this->normalize($name);
    }

    public function attribute(string|BackedEnum $name, string|BackedEnum $value): static
    {
        $this->attributes[$this->normalize($name)] = $this->normalize($value);

        return $this;
    }

    /**
     * Run the closure inside a span and return its result untouched.
     *
     * The span is *activated* for the duration of the call, so anything
     * auto-instrumented underneath (SQL, HTTP, queue dispatches) nests below it
     * instead of hanging off the request root. Detaching the scope and ending
     * the span therefore has to happen in `finally` — a throwing closure that
     * skipped either would leave the context stack corrupted for the rest of
     * the request, not merely lose one span.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $fn
     * @return TReturn
     */
    public function time(Closure $fn): mixed
    {
        $span = $this->tracer()
            ->spanBuilder($this->name)
            ->setAttributes($this->attributes)
            ->startSpan();

        $scope = $span->activate();

        try {
            return $fn();
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR);

            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    /**
     * Note what is *not* here: `Metrics::prefixed()`. The configured metric name
     * prefix is a Prometheus naming convention and means nothing to a trace
     * backend, so span names are passed through verbatim.
     */
    protected function tracer(): TracerInterface
    {
        return $this->tracerProvider()->getTracer(
            config('metrics.tracer_name', 'motuslogistik/metrics'),
        );
    }

    /**
     * Prefer a container-bound provider (how tests inject an in-memory one),
     * otherwise the global one. With no SDK registered Globals returns a no-op
     * tracer and every call below becomes a cheap no-op — intended, so call
     * sites never have to ask whether tracing is enabled.
     */
    protected function tracerProvider(): TracerProviderInterface
    {
        if (app()->bound(TracerProviderInterface::class)) {
            return app(TracerProviderInterface::class);
        }

        return Globals::tracerProvider();
    }

    protected function normalize(string|BackedEnum $value): string
    {
        return $value instanceof BackedEnum ? (string) $value->value : $value;
    }
}
