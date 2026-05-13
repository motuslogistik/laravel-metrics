# Changelog

All notable changes to `metrics` will be documented in this file.

## [Unreleased]

### Removed

- `Histogram` metric type and the `histogram()` helper. The previous implementation emitted `# TYPE x histogram` with a single value line, which is not a valid Prometheus histogram. Use `Gauge` instead, or wait for a proper histogram implementation.

### Changed

- **Breaking:** `observe(Closure)` has moved from `Histogram` to `Gauge` and now records the duration in **seconds as a float** (Prometheus convention) instead of milliseconds as an int. Dashboards reading the old values will be off by a factor of 1000.

### Fixed

- `RedisStore::iterator()` now strips the Redis connection prefix from keys returned by `KEYS` before passing them to `MGET`, fixing a double-prefixing bug that caused all values to come back as `null` when a connection-level prefix was configured.
