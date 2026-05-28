<?php

use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use motuslogistik\Metrics\Metrics;
use motuslogistik\Metrics\MetricsServiceProvider;

/**
 * Re-boot the package provider after mutating config, so packageBooted() reads
 * the auto-track flags we set in-test (the default boot in setUp ran with
 * auto_track_jobs off).
 */
function rebootProvider($app): void
{
    $provider = new MetricsServiceProvider($app);
    $provider->register();
    $provider->boot();
}

function fakeJob(string $name, string $queue = 'default', bool $failed = false): Job
{
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('resolveName')->andReturn($name);
    $job->shouldReceive('getQueue')->andReturn($queue);
    $job->shouldReceive('hasFailed')->andReturn($failed);
    $job->shouldReceive('payload')->andReturn([]);

    return $job;
}

it('records a histogram for a processed job', function () {
    Metrics::trackQueueJobs();

    $job = fakeJob('App\\Jobs\\ImportJob', 'imports');

    event(new JobProcessing('redis', $job));
    usleep(5_000);
    event(new JobProcessed('redis', $job));

    $metric = $this->metric('queue_job_seconds');

    expect($metric)->not->toBeNull();

    $point = $this->dataPoint($metric, [
        'job' => 'App\\Jobs\\ImportJob',
        'queue' => 'imports',
        'connection' => 'redis',
        'status' => 'success',
    ]);

    expect($point)->not->toBeNull()
        ->and($point->count)->toBe(1)
        ->and($point->sum)->toBeGreaterThan(0.0);
});

it('labels a thrown job as error', function () {
    Metrics::trackQueueJobs();

    $job = fakeJob('App\\Jobs\\ChangeOrderJob');

    event(new JobProcessing('redis', $job));
    event(new JobExceptionOccurred('redis', $job, new RuntimeException('boom')));

    $point = $this->dataPoint($this->metric('queue_job_seconds'), ['status' => 'error']);

    expect($point)->not->toBeNull()->and($point->count)->toBe(1);
});

it('skips jobs passed to except()', function () {
    Metrics::trackQueueJobs()->except('App\\Jobs\\NoisyJob');

    $job = fakeJob('App\\Jobs\\NoisyJob');

    event(new JobProcessing('redis', $job));
    event(new JobProcessed('redis', $job));

    expect($this->metric('queue_job_seconds'))->toBeNull();
});

it('tracks only the allow-listed jobs when only() is set', function () {
    Metrics::trackQueueJobs()->only('App\\Jobs\\WantedJob');

    $wanted = fakeJob('App\\Jobs\\WantedJob');
    $other = fakeJob('App\\Jobs\\OtherJob');

    event(new JobProcessing('redis', $other));
    event(new JobProcessed('redis', $other));
    event(new JobProcessing('redis', $wanted));
    event(new JobProcessed('redis', $wanted));

    $metric = $this->metric('queue_job_seconds');

    expect($metric)->not->toBeNull()
        ->and($this->dataPoint($metric, ['job' => 'App\\Jobs\\WantedJob']))->not->toBeNull()
        ->and($this->dataPoint($metric, ['job' => 'App\\Jobs\\OtherJob']))->toBeNull();
});

it('honors a custom histogram name', function () {
    Metrics::trackQueueJobs()->name('job_runtime_seconds');

    $job = fakeJob('App\\Jobs\\ImportJob');

    event(new JobProcessing('redis', $job));
    event(new JobProcessed('redis', $job));

    expect($this->metric('job_runtime_seconds'))->not->toBeNull();
});

it('does not track jobs by default (auto_track_jobs off)', function () {
    expect(config('metrics.auto_track_jobs'))->toBeFalse();

    $job = fakeJob('App\\Jobs\\ImportJob');

    event(new JobProcessing('redis', $job));
    event(new JobProcessed('redis', $job));

    expect($this->metric('queue_job_seconds'))->toBeNull();
});

it('auto-tracks jobs when auto_track_jobs is enabled in config', function () {
    config()->set('metrics.auto_track_jobs', true);
    rebootProvider($this->app);

    $job = fakeJob('App\\Jobs\\ImportJob');

    event(new JobProcessing('redis', $job));
    event(new JobProcessed('redis', $job));

    expect($this->metric('queue_job_seconds'))->not->toBeNull();
});

it('honors auto_track_jobs_except from config', function () {
    config()->set('metrics.auto_track_jobs', true);
    config()->set('metrics.auto_track_jobs_except', ['App\\Jobs\\NoisyJob']);
    rebootProvider($this->app);

    $tracked = fakeJob('App\\Jobs\\ImportJob');
    $noisy = fakeJob('App\\Jobs\\NoisyJob');

    event(new JobProcessing('redis', $tracked));
    event(new JobProcessed('redis', $tracked));
    event(new JobProcessing('redis', $noisy));
    event(new JobProcessed('redis', $noisy));

    $metric = $this->metric('queue_job_seconds');

    expect($this->dataPoint($metric, ['job' => 'App\\Jobs\\ImportJob']))->not->toBeNull()
        ->and($this->dataPoint($metric, ['job' => 'App\\Jobs\\NoisyJob']))->toBeNull();
});
