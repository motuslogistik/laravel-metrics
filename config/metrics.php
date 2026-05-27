<?php

// config for motuslogistik/Metrics

return [
    /*
     | Instrumentation scope name passed to the OTel MeterProvider. Surfaces
     | in exported metrics as `otel.scope.name`.
     */
    'meter_name' => 'motuslogistik/metrics',

    /*
     | Default histogram bucket boundaries. OTel's own defaults
     | (0, 5, 10, 25, ... 10000) assume milliseconds, which mismatches the
     | Prometheus `_seconds` convention. These boundaries target
     | seconds-scale latency, the common case for this package.
     |
     | Ignored when OTEL_EXPORTER_OTLP_METRICS_DEFAULT_HISTOGRAM_AGGREGATION
     | is set to `base2_exponential_bucket_histogram` — `Metrics::histogram()`
     | skips the advisory in that mode so the env-level exponential
     | preference wins. (The PHP SDK otherwise honors a per-instrument
     | advisory over the env preference, which silently keeps you on classic
     | histograms even when you've asked for exponential.)
     */
    'default_histogram_buckets' => [0.001, 0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10],

    /*
     | Per-histogram bucket overrides, keyed by exact metric name. Use when a
     | specific histogram is on a different scale (e.g. byte sizes, or
     | latencies known to be sub-millisecond / multi-minute).
     |
     | The advisory is applied at first instrument creation per process; the
     | OTel SDK caches the instrument by name, so values must stay stable
     | across deploys for histogram_quantile() to remain meaningful.
     */
    'histogram_buckets' => [
        // 'metric_name' => [0.0001, 0.001, 0.01, 0.1, 1],
    ],

    /*
     | Force-flush the OTel MeterProvider after every queue job. The OTel PHP
     | SDK uses an ExportingReader with no periodic export, so in long-running
     | queue workers metrics would otherwise only flush when the worker dies.
     | The listener fires for every queue job (including the sync driver);
     | forceFlush() is a no-op when there's nothing to push, so the overhead
     | is negligible.
     |
     | Disable if you have your own flushing strategy (periodic timer,
     | manual flush) or don't want listeners on the queue lifecycle.
     */
    'flush_on_queue_job' => true,
];
