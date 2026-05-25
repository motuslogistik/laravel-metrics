# Changelog

All notable changes to `metrics` will be documented in this file.

## [Unreleased]

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

- **Breaking:** `observe(Closure)` is gone from `Gauge`. Histograms are the right instrument for distribution-shaped timing data — use `histogram(...)->observe(...)` instead.
- `observe(Closure)` records the duration in **seconds as a float** (Prometheus convention) instead of milliseconds as an int. Dashboards reading the old values will be off by a factor of 1000.
- `RedisStore::iterator()` (now removed) had a double-prefixing bug fixed before the OTel migration.
