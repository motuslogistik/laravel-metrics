<?php

declare(strict_types=1);

namespace motuslogistik\Metrics\Queue;

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Queue;

/**
 * Emits one latency histogram for every processed queue job, hooking Laravel's
 * queue lifecycle once instead of instrumenting each job class by hand.
 *
 * What it measures: the full `JobProcessing` → `JobProcessed`/`JobExceptionOccurred`
 * window — i.e. everything inside `Worker::process()`'s `$job->fire()`:
 * payload deserialization (including `SerializesModels` model re-hydration),
 * the job-middleware pipeline, the `handle()` body, and post-handle chain/batch
 * bookkeeping. It does NOT include queue wait time or the payload pop. If you
 * want strictly the `handle()` method body, use `observe($job, 'handle')` instead.
 *
 * Call once (typically a service provider's `boot()`); under Octane that's once
 * per worker, which is what you want. Calling it again registers a second set of
 * listeners and double-counts.
 */
class QueueJobTracker
{
    /** @var array<int, int> hrtime() starts keyed by spl_object_id() of the Job. */
    protected array $starts = [];

    protected string $name = 'queue_job_seconds';

    /** @var list<class-string> */
    protected array $only = [];

    /** @var list<class-string> */
    protected array $except = [];

    public function __construct()
    {
        Queue::before(fn (JobProcessing $e) => $this->start($e->job));
        Queue::after(fn (JobProcessed $e) => $this->finish($e->job, $e->connectionName, $e->job->hasFailed() ? 'error' : 'success'));
        Queue::exceptionOccurred(fn (JobExceptionOccurred $e) => $this->finish($e->job, $e->connectionName, 'error'));
    }

    /**
     * Override the histogram name (default `queue_job_seconds`).
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Track only these job classes. Mutually combined with except(); only wins
     * as an allow-list when set.
     *
     * @param  class-string  ...$jobs
     */
    public function only(string ...$jobs): self
    {
        $this->only = [...$this->only, ...$jobs];

        return $this;
    }

    /**
     * Skip these job classes (e.g. a high-frequency heartbeat job whose timing
     * isn't worth the series). Matched against the job's resolved name.
     *
     * @param  class-string  ...$jobs
     */
    public function except(string ...$jobs): self
    {
        $this->except = [...$this->except, ...$jobs];

        return $this;
    }

    protected function start(Job $job): void
    {
        if (! $this->tracks($job->resolveName())) {
            return;
        }

        $this->starts[spl_object_id($job)] = hrtime(true);
    }

    protected function finish(Job $job, string $connection, string $status): void
    {
        $key = spl_object_id($job);
        $start = $this->starts[$key] ?? null;
        unset($this->starts[$key]);

        if ($start === null) {
            return;
        }

        histogram($this->name, [
            'job' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'connection' => $connection,
            'status' => $status,
        ])->record((hrtime(true) - $start) / 1e9);
    }

    protected function tracks(string $job): bool
    {
        if ($this->only !== [] && ! in_array($job, $this->only, true)) {
            return false;
        }

        return ! in_array($job, $this->except, true);
    }
}
