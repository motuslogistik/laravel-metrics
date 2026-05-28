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

    /*
     | Force-flush the OTel MeterProvider on Octane lifecycle events. The OTel
     | PHP SDK's ExportingReader has no periodic export, so under long-lived
     | Octane (Swoole/RoadRunner) workers HTTP-recorded metrics would buffer in
     | memory until the worker recycles — and be lost on a crash. This listens
     | on Octane's request/task/tick termination events (which fire after the
     | response is sent, so no added latency) plus worker shutdown. Harmless
     | when Octane isn't installed; the events are simply never dispatched.
     |
     | Disable if you run your own periodic flush.
     */
    'flush_on_octane' => true,

    /*
     | Auto-instrument every queue job with a `queue_job_seconds` latency
     | histogram (labels: job, queue, connection, status) without wiring
     | `Metrics::trackQueueJobs()` by hand in a service provider. Off by default;
     | flip to true and you get per-job timing for the whole queue.
     |
     | This measures the full job-processing window (deserialization +
     | middleware + handle() + bookkeeping), not just handle() — see
     | Metrics::trackQueueJobs() in the README. If you enable this, do NOT also
     | call Metrics::trackQueueJobs() yourself, or jobs get counted twice.
     */
    'auto_track_jobs' => false,

    /*
     | Job classes to exclude when `auto_track_jobs` is on — e.g. high-frequency
     | heartbeat jobs whose timing isn't worth a time series. Matched against the
     | job's resolved class name.
     */
    'auto_track_jobs_except' => [
        // App\Jobs\HeartbeatJob::class,
    ],
];
