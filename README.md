# Metrics for Motus

> **Internal package** — proprietary to Motus Logistik. Not licensed for external use. See [LICENSE.md](LICENSE.md).

Lightweight application metrics for Laravel. Counters, gauges and histograms written to a pluggable store (APCu, in-memory, or Swoole Table), with Prometheus/Grafana-compatible type metadata.

## Installation

```bash
composer require motuslogistik/metrics
```

Publish the config:

```bash
php artisan vendor:publish --tag="metrics-config"
```

```php
// config/metrics.php
return [
    'store'  => \motuslogistik\Metrics\Stores\ArrayStore::class,
    'prefix' => 'metrics|',
];
```

### Stores

- `ArrayStore` — in-memory, per-request. Default. Good for tests.
- `APCStore` — shared memory via the `apcu` extension. Use in classic FPM.
  - Requires `ext-apcu` and `apc.enable_cli=1` if you run metrics from CLI.
- `SwooleTableStore` — shared memory across Octane workers via Swoole Tables. Use with Laravel Octane / Swoole.
  - Requires `ext-swoole`. Size is fixed at boot via `metrics.swoole.size`.

Swap stores by setting `'store'` in the config file.

## Usage

Three metric types, three helpers:

```php
counter('orders_created', ['status' => 'paid'])->incr();

gauge('cpu_usage', ['host' => 'web1'])->record(0.83);

histogram('http_latency', ['path' => '/home'])->record(123);
```

Counters expose three operations:

```php
counter('orders_created')->incr();        // +1
counter('queue_depth')->incr(5);          // +5
counter('queue_depth')->decr();           // -1
counter('queue_depth')->decr(3);          // -3
counter('orders_total')->set(42);         // overwrite to 42
```

`incr()` / `decr()` adjust the stored value relatively. `set($value)` overwrites it — useful when the count is sampled from another source rather than tallied locally.

`record()` (no args) is kept as an alias for `incr()` so existing callsites keep working.

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

`observe()` times the closure, records the duration (ms), and returns the closure's result.

### Backed enums

Everywhere a string is accepted (name, label name, label value), a `BackedEnum` works too:

```php
enum Status: string { case Paid = 'paid'; }

counter('orders_created')
    ->label('status', Status::Paid)
    ->incr();
```

Int-backed enums are coerced to string (`Status::One = 1` → `"1"`).

### Reserved characters

`|`, `;`, and `=` are key delimiters and may not appear in any input. Passing them throws `InvalidArgumentException`. Colons (`:`) are allowed — Prometheus treats them as valid in metric names (used for recording rules).

## Scrape endpoint

A Prometheus-format scrape endpoint is registered automatically at `/metrics`:

```
# TYPE orders_created counter
orders_created{status="paid"} 3

# TYPE cpu_usage gauge
cpu_usage{host="web1"} 0.83
```

Configure via `metrics.route`:

```php
'route' => [
    'enabled'    => true,
    'path'       => '/metrics',
    'middleware' => ['auth:api'], // e.g. protect behind a middleware
],
```

Set `'enabled' => false` to opt out and register your own route pointing at `MetricsController::class`.

## Key format

```
<prefix><name>;<label>=<value>;<label>=<value>
```

Labels are sorted alphabetically so label ordering at the call site never affects the key. Default prefix is `metrics|`, configurable via `metrics.prefix` — change it if you share an APCu pool with other apps.

Each metric also writes a type hint at `<prefix>__types|<name>` (`counter` / `gauge` / `histogram`). This is for exporters to emit correct `# TYPE` lines — internally, nothing branches on it.

## Testing

```bash
composer test
```

APCu tests run only when APCu is enabled for CLI:

```bash
php -d apc.enable_cli=1 vendor/bin/pest
```

Without the flag, they self-skip.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Patrik Malmström](https://github.com/popsork)
- [All Contributors](../../contributors)

## License

Proprietary. Copyright © 2026 Motus Logistik. See [LICENSE.md](LICENSE.md).
