# Metrics for Motus

> **Internal package** — proprietary to Motus Logistik. Not licensed for external use. See [LICENSE.md](LICENSE.md).

A thin Laravel-friendly facade over the [OpenTelemetry PHP SDK](https://opentelemetry.io/docs/languages/php/). Counters, gauges and timing helpers that emit OTel instruments — the recording layer, exporter, and aggregation are handled by OTel.

## Architecture

```
Laravel app  ──(this package)──>  OTel SDK  ──OTLP──>  OTel Collector  ──>  Prometheus / Grafana / …
```

In classic PHP-FPM (or any forking model) each request is a fresh process, so per-process metric state cannot be scraped meaningfully. **You must run an OpenTelemetry Collector** somewhere reachable (sidecar container, host-level daemon, …) — that's where requests push to, and that's what Prometheus scrapes.

This is a breaking change from the pre-1.x line, which used APCu / Redis / Swoole shared memory and exposed `/metrics` directly from PHP. See [CHANGELOG.md](CHANGELOG.md).

## Installation

```bash
composer require motuslogistik/metrics
```

You also need an OpenTelemetry SDK bootstrap. The most common setup is environment-driven autoload via `open-telemetry/opentelemetry-auto-laravel`, or manual bootstrap in a service provider. At minimum the SDK needs:

```env
OTEL_PHP_AUTOLOAD_ENABLED=true
OTEL_SERVICE_NAME=your-app
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_METRICS_EXPORTER=otlp
OTEL_LOGS_EXPORTER=none
OTEL_TRACES_EXPORTER=none   # unless you also want traces
```

### Recommended OTel env vars

For PHP-FPM (and any forking model), two extra env vars matter a lot:

```env
# Delta temporality. Each export reports the delta since the last export
# instead of a cumulative total. This avoids the "stuck-at-1" symptom where
# Laravel re-bootstraps the container per request and the meter state is
# reset before the cumulative count can grow. Requires a backend that can
# consume delta OTLP (most can; some need the Collector's
# `deltatocumulative` processor in front for native PromQL `rate()` to work).
OTEL_EXPORTER_OTLP_METRICS_TEMPORALITY_PREFERENCE=delta

# Use exponential (base-2) histograms instead of explicit buckets. One layout
# covers nanoseconds-to-minutes with consistent ~5% relative error, no
# per-metric bucket tuning needed. Requires backend support for OTLP
# exponential / Prometheus native histograms (VictoriaMetrics, recent
# Prometheus, Groundcover — verify before relying on it).
OTEL_EXPORTER_OTLP_METRICS_DEFAULT_HISTOGRAM_AGGREGATION=base2_exponential_bucket_histogram
```

Both are optional. If you leave them unset, you get cumulative temporality + explicit-bucket histograms — the defaults are fine for long-lived processes (CLI workers, daemons) but fragile under PHP-FPM. Set them.

### Package config

```bash
php artisan vendor:publish --tag="metrics-config"
```

```php
// config/metrics.php
return [
    'meter_name' => 'motuslogistik/metrics',

    // Default explicit bucket boundaries (seconds scale). Ignored if you've
    // switched to exponential histograms via the env var above.
    'default_histogram_buckets' => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],

    // Per-histogram bucket overrides, keyed by exact metric name. Use for
    // metrics on a different scale than seconds (byte sizes, item counts,
    // sub-millisecond timings, etc.).
    'histogram_buckets' => [
        // 'payload_size_bytes' => [256, 1024, 4096, 16384, 65536, 262144, 1048576],
    ],
];
```

### PHP-FPM and queue worker caveats

A few sharp edges to be aware of:

- **Instruments are cached by name per process.** The OTel SDK creates an instrument on first `histogram($name)` call per worker and reuses it for the worker's lifetime. Bucket boundaries set via `default_histogram_buckets` / `histogram_buckets` are honored on that first creation only — changes require a worker restart (redeploy).
- **Bucket layout changes invalidate historical series.** Old data lives in the backend under the old `le` labels; new exports use the new labels. Queries spanning the transition will mix two layouts. Either query strictly after the cutover, or rename the metric on the switch.
- **Worker recycling shows as counter resets.** With cumulative temporality, a worker dying mid-window drops its accumulated count to 0 in the next worker. PromQL `rate()` is reset-aware so this usually doesn't hurt, but it's another reason delta temporality is easier to reason about under FPM.
- **Queue workers auto-flush after every job.** The OTel PHP SDK uses an `ExportingReader` with no periodic export, so without this metrics from long-running workers would only flush on worker death. The package registers `Queue::after` / `Queue::failing` listeners that call `MeterProvider::forceFlush()` after each job. Set `metrics.flush_on_queue_job` to `false` to disable.

## Usage

Three metric types, three helpers:

```php
counter('orders_created', ['status' => 'paid'])->incr();

gauge('cpu_usage', ['host' => 'web1'])->record(0.83);

histogram('http_latency', ['path' => '/home'])->record(123);
```

Counters are monotonic — only `incr()`. Under the hood they emit to an OTel `Counter`, which surfaces in Prometheus/Groundcover with `# TYPE counter` so PromQL's `rate()` and `increase()` apply naturally:

```php
counter('orders_created')->incr();        // +1
counter('orders_created')->incr(5);       // +5
```

For up-and-down values like queue depth, use a `gauge()` instead — counters are for event counting only.

`record()` (no args) is kept as an alias for `incr()` so existing call sites keep working.

The label array is a shortcut; `->label()` still works for dynamic labels or longer chains:

```php
counter('orders_created')
    ->label('status', $order->status)
    ->label('channel', $channel)
    ->incr();
```

### Builder style

`metric()` returns an untyped `PendingMetric`. It has no `record()` — pick a type first:

```php
metric('orders_created')
    ->label('status', 'paid')
    ->counter()
    ->incr();
```

Labels accumulated on `metric()` carry over when you call `->counter()`, `->gauge()`, or `->histogram()`.

### Timing closures

```php
histogram('http_render')
    ->label('path', '/home')
    ->time(fn () => renderHomepage());
```

`time()` runs the closure, records the duration (seconds, float), and returns the closure's result. It lives on `histogram()` only — histograms are the right instrument for distribution-shaped data like latencies (you get count, sum, buckets, percentiles).

### `observe()` — auto-instrument a method

For a class method you'd otherwise wrap by hand in every call site, the `observe()` helper hooks it once and emits a latency histogram for every invocation. Requires the [`opentelemetry` PHP extension](https://opentelemetry.io/docs/zero-code/php/setup/#install-the-extension); without it, calls log a warning and no-op.

```php
// In a service provider's register()
observe(GetPersonalAccessTokenQuery::class, '__invoke')
    ->name('get_personal_access_token_query_seconds');
```

That emits `get_personal_access_token_query_seconds` (a histogram) with a `status` label (`success` / `error` / `__error__`) on every call to `GetPersonalAccessTokenQuery::__invoke`.

**Labels from the invocation:**

Label closures get named-argument injection from `(instance, params, return, exception)` — declare only what you need:

```php
observe(Order::class, 'save')
    ->name('order_save_seconds')
    ->label('customer_id', fn ($instance) => $instance->customer_id)
    ->label('was_new', fn ($return) => $return === true);
```

**Custom success/error logic:**

By default, "success" means the method returned without throwing. Override with `successResolver()` when that's not enough — e.g. a method that returns `false` on validation failure:

```php
observe(SomeJob::class, 'handle')
    ->name('some_job_seconds')
    ->successResolver(fn ($return, $exception) => $exception === null && $return !== false);
```

Status values you'll see in PromQL:
- `success` — `successResolver` returned truthy (or no exception by default)
- `error` — `successResolver` returned falsy (or an exception was thrown)
- `__error__` — a label callback or the success resolver itself threw. Inspect logs.

**Registering many at once:**

Iterate a list:

```php
foreach ([StepA::class, StepB::class, StepC::class] as $step) {
    observe($step, '__invoke')
        ->name('pipeline_step_seconds')
        ->label('step', fn ($instance) => class_basename($instance));
}
```

One metric, dimensional `step` label — PromQL aggregates across or drills into individual steps with `sum by (step)`.

**Caveats:**

- The OTel SDK caches instruments by name *per process*, so the histogram's bucket layout is fixed at first call. If you change `default_histogram_buckets`, restart workers.
- `observe()` registers the hook once at call time (typically in a service provider). The hook fires every time the target method is invoked thereafter.
- The label value goes through OTel's attribute system; keep cardinality bounded. Don't put user IDs or request IDs as label values — use them in span attributes / logs instead.

### `gauge()->set()`

`set()` is an alias for `record()` on `Gauge`, kept so call sites migrating from the old `counter()->set($n)` API need only swap the helper:

```php
counter('orders_total')->set(42);   // old (removed)
gauge('orders_total')->set(42);     // new
```

### Backed enums

Everywhere a string is accepted (name, label name, label value), a `BackedEnum` works too:

```php
enum Status: string { case Paid = 'paid'; }

counter('orders_created')
    ->label('status', Status::Paid)
    ->incr();
```

Int-backed enums are coerced to string (`Status::One = 1` → `"1"`).

### `->global()` is now a no-op

In the previous architecture `->global()` routed a metric to a Redis store shared across hosts. With OTel that distinction disappears: every metric flushes via OTLP to the Collector, which is already global. The method is kept on `PendingMetric` so existing call sites don't break, but it does nothing.

## Removed in the OTel migration

- **`counter()->set($n)`** — OTel counters are delta-only. Use a `gauge()` for absolute values you sample from elsewhere.
- **`/metrics` route** — the Collector exposes Prometheus now.
- **`Store` contract + all stores** — OTel owns aggregation. `ext-apcu` is no longer required.
- **Reserved-character validation on names/labels** (`|;=`) — that was only needed for the old key format.

If you need any of these, pin to a pre-1.x version of this package.

## Testing

```bash
composer test
```

Tests use OTel's `InMemoryExporter` + `ExportingReader` — no Collector or network required. See `tests/TestCase.php` for the wiring.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Patrik Malmström](https://github.com/popsork)
- [All Contributors](../../contributors)

## License

Proprietary. Copyright © 2026 Motus Logistik. See [LICENSE.md](LICENSE.md).
