# Changelog

All notable changes to `metrics` will be documented in this file.

## [Unreleased]

### Added

- `Metrics::trackQueueJobs()` — hooks Laravel's queue lifecycle once and emits a `queue_job_seconds` histogram (labels: `job`, `queue`, `connection`, `status`) for every processed job, instead of wiring `observe()` per job class. Chain `->except(...)` / `->only(...)` to scope it and `->name(...)` to rename. Measures the full `$job->fire()` window (deserialization + middleware + `handle()` + bookkeeping), distinct from `observe($job, 'handle')` which measures the method body alone.
- `auto_track_jobs` / `auto_track_jobs_except` config — enable `trackQueueJobs()` for the whole app without touching a service provider. Both default off; the package calls `Metrics::trackQueueJobs()->except(...)` on boot when enabled.
- `Metrics::flush()` — force-flushes the OTel `MeterProvider`. Use in long-running processes that aren't queue workers (AMQP consumers, daemons) where the SDK's `ExportingReader` would otherwise hold samples until the process dies.
- `observe(...)->flushAfter()` — chain on an `observe()` registration to call `Metrics::flush()` after each recorded sample. Same use case as above, for when the observed method *is* the loop body.
- **Laravel Octane support, out of the box.** The package now flushes the `MeterProvider` on Octane's `RequestTerminated`, `TaskTerminated`, `TickTerminated` and `WorkerStopping` events, so HTTP-recorded metrics no longer buffer indefinitely in long-lived Swoole/RoadRunner workers. Controlled by the new `metrics.flush_on_octane` config flag (default `true`); harmless when Octane isn't installed.
- `observe()` timing is now coroutine-safe — the per-method start times are keyed by Swoole coroutine id, so durations stay correct when calls interleave under `Octane::concurrently()` or manual coroutines (outside coroutines the behavior is unchanged).

### Documented

- The PHP OTel SDK (≥1.14 observed) does **not** implement exponential histograms: `OTEL_EXPORTER_OTLP_METRICS_DEFAULT_HISTOGRAM_AGGREGATION` is a no-op (`MeterProviderFactory::create()` carries a `@todo`) and no `Base2ExponentialBucketHistogramAggregation` class ships in the SDK. README and config comments updated to drop the earlier suggestion to set that env var and to call out explicit buckets as the only available option in PHP until upstream lands the work.

### Changed

- **Breaking:** the package is now a thin facade over the OpenTelemetry PHP SDK. Helpers (`counter()`, `gauge()`, `metric()`, `->label()`, `->incr()`, `->decr()`, `->record()`, `->observe()`) keep their signatures, but recording now emits via an OTel `Meter` instead of writing to an in-package store. Cross-process aggregation now requires running an **OpenTelemetry Collector** as a sidecar; PHP pushes via OTLP, the Collector exposes Prometheus.

### Removed

- **Breaking:** `Counter::set()`. OTel `UpDownCounter` is delta-only and has no clean equivalent. Track absolute values with a `Gauge` instead.
- **Breaking:** `/metrics` Prometheus scrape route, `MetricsController`, and `PrometheusExporter`. The Collector is now the scrape target.
- **Breaking:** the `Store` contract and all implementations (`ArrayStore`, `APCStore`, `RedisStore`, `SwooleTableStore`). The `ext-apcu` requirement is dropped.
- **Breaking:** `metrics.store`, `metrics.global_store`, `metrics.prefix`, `metrics.redis`, `metrics.swoole`, and `metrics.route` config keys.
- Reserved-character validation on names/labels (`|;=`). Those characters were only reserved because of the in-package key format, which no longer exists.

### Deprecated

- `->global()` is now a no-op. Kept on `PendingMetric` so existing call sites don't break. Once metrics land in the Collector they are aggregated across processes and hosts automatically.

### Added

- `histogram()` helper, restored on top of OTel's real `Histogram` instrument (bucket-based aggregation, `sum`/`count` data points). Exposes `record($value)` and `observe(Closure)`. `metric()->histogram()` also works.
- `Gauge::set()` — alias for `record()`, to ease migration of call sites moving from the removed `Counter::set()`.

### Previous unreleased changes

- **Breaking:** `Counter::decr()` is gone. `counter()` now emits an OTel `Counter` (monotonic) so Prometheus/Groundcover sees `# TYPE counter` and `rate()`/`increase()` work as intended. The previous OTel-`UpDownCounter` backing made the type surface as `gauge`, defeating the purpose. For up-and-down values, use `gauge()->set()`.
- **Breaking:** `observe(Closure)` is gone from `Gauge`. Histograms are the right instrument for distribution-shaped timing data — use `histogram(...)->observe(...)` instead.
- `observe(Closure)` records the duration in **seconds as a float** (Prometheus convention) instead of milliseconds as an int. Dashboards reading the old values will be off by a factor of 1000.
- `RedisStore::iterator()` (now removed) had a double-prefixing bug fixed before the OTel migration.
