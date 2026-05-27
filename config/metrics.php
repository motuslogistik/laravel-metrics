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
     | Note: the OTel PHP SDK (≥1.14 observed) does not implement
     | exponential histograms — the relevant env var is a no-op and there
     | is no Base2ExponentialBucketHistogramAggregation class to route to
     | via a View. Explicit buckets are the only option in PHP today, so
     | size them generously for any metric whose range is hard to predict.
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
