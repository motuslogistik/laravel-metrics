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

Publish the package config (optional — only one knob):

```bash
php artisan vendor:publish --tag="metrics-config"
```

```php
// config/metrics.php
return [
    'meter_name' => 'motuslogistik/metrics',
];
```

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
    ->observe(fn () => renderHomepage());
```

`observe()` times the closure, records the duration (seconds, float), and returns the closure's result. It lives on `histogram()` only — histograms are the right instrument for distribution-shaped data like latencies (you get count, sum, buckets, percentiles).

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
