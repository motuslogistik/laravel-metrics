<?php

use motuslogistik\Metrics\Hooks\Observer;

/**
 * Drives Observer::pre()/post() with a scripted coroutine id and captures the
 * recorded durations, so we can assert each post() is paired with its own pre()
 * even when calls interleave. The opentelemetry extension is absent in tests, so
 * the parent constructor short-circuits before registering a real hook.
 */
class CoroutineProbeObserver extends Observer
{
    public int $cid = -1;

    /** @var array<int, list<float>> */
    public array $captured = [];

    protected function currentCid(): int
    {
        return $this->cid;
    }

    protected function record(array $labels, float $duration): void
    {
        $this->captured[$this->cid][] = $duration;
    }

    public function enter(int $cid): void
    {
        $this->cid = $cid;
        $this->pre(null, []);
    }

    public function leave(int $cid): void
    {
        $this->cid = $cid;
        $this->post(null, [], null, null);
    }
}

it('keeps coroutine timings isolated under interleaved pre/post', function () {
    $observer = new CoroutineProbeObserver(stdClass::class, 'handle', 'coroutine_latency');

    $observer->enter(1);   // coroutine 1 starts
    usleep(60_000);        // 60ms elapses while only coroutine 1 is open
    $observer->enter(2);   // coroutine 2 starts
    $observer->leave(1);   // coroutine 1 finishes — should measure ~60ms
    usleep(10_000);
    $observer->leave(2);   // coroutine 2 finishes — should measure ~10ms

    // Per-coroutine stacks pair each post with its own pre. A single shared LIFO
    // would invert these: coroutine 1's post would pop coroutine 2's later start.
    expect($observer->captured[1][0])->toBeGreaterThan($observer->captured[2][0]);
});

it('times sequential nested calls correctly outside any coroutine', function () {
    $observer = new CoroutineProbeObserver(stdClass::class, 'handle', 'seq_latency');

    // cid -1 is the no-Swoole bucket; nested calls must still pair LIFO.
    $observer->enter(-1);  // outer
    $observer->enter(-1);  // inner
    usleep(10_000);
    $observer->leave(-1);  // inner — short
    usleep(40_000);
    $observer->leave(-1);  // outer — longer

    [$inner, $outer] = $observer->captured[-1];

    expect($outer)->toBeGreaterThan($inner);
});
