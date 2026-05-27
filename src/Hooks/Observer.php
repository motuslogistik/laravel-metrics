<?php

declare(strict_types=1);

namespace motuslogistik\Metrics\Hooks;

use Closure;
use Illuminate\Support\Facades\Log;
use motuslogistik\Metrics\Metrics;
use ReflectionFunction;
use Throwable;

use function OpenTelemetry\Instrumentation\hook;

class Observer
{
    protected static bool $extensionWarned = false;
    protected array $startStack = [];
    protected array $labels = [];
    protected ?Closure $successResolver = null;
    /** @var array<int, list<string>> Keyed by spl_object_id($callback); orphaned on replacement. */
    protected array $callbackArgumentCache = [];
    protected bool $nameWarned = false;
    protected bool $flushAfter = false;

    public function __construct(
        /**
         * @var class-string $class
         */
        protected string $class,
        protected string $method,
        protected ?string $name = null,
    ) {
        if (!extension_loaded('opentelemetry')) {
            if (!self::$extensionWarned) {
                Log::warning(
                    'Observer for ' . $this->class . '::' . $this->method . ' cannot be initiated, ' .
                    'opentelemetry extension not loaded.',
                );
                self::$extensionWarned = true;
            }

            return;
        }

        hook(
            class: $this->class,
            function: $this->method,
            pre: fn($instance, array $params) => $this->pre($instance, $params),
            post: fn($instance, array $params, $return, ?Throwable $e) => $this->post($instance, $params, $return, $e),
        );
    }

    protected function pre($instance, array $params): void
    {
        $this->startStack[] = hrtime(true);
    }

    protected function post($instance, array $params, $return, ?Throwable $e): void
    {
        $start = array_pop($this->startStack);
        if ($start === null) {
            return;
        }

        if ($this->name === null) {
            if (!$this->nameWarned) {
                Log::warning('Observer for ' . $this->class . '::' . $this->method . ' is missing name.');
                $this->nameWarned = true;
            }

            return;
        }

        $availableArgs = [
            'instance' => $instance,
            'params' => $params,
            'return' => $return,
            'exception' => $e,
        ];

        $duration = (hrtime(true) - $start) / 1e9;
        $labels = $this->resolveLabels($availableArgs);
        $status = $this->resolveStatus($availableArgs);

        histogram($this->name, $labels + ['status' => $status])->record($duration);

        if ($this->flushAfter) {
            Metrics::flush();
        }
    }

    protected function resolveLabels(array $availableArgs): array
    {
        if (empty($this->labels)) {
            return [];
        }

        $resolved = [];
        foreach ($this->labels as $label => $callback) {
            $args = $this->resolveCallbackArguments($callback, $availableArgs);

            try {
                $resolved[$label] = $callback(...$args);
            } catch (Throwable $e) {
                Log::warning(
                    'Failed to resolve label "' . $label . '" for ' .
                    $this->class . '::' . $this->method . ': ' . $e->getMessage(),
                );
                $resolved[$label] = '__error__';
            }
        }

        return $resolved;
    }

    protected function resolveCallbackArguments(Closure $callback, array $availableArgs): array
    {
        $args = [];
        foreach ($this->resolveCallbackArgumentsMap($callback) as $name) {
            $args[$name] = $availableArgs[$name] ?? null;
        }

        return $args;
    }

    protected function resolveCallbackArgumentsMap(Closure $callback): array
    {
        $cacheKey = spl_object_id($callback);
        if (array_key_exists($cacheKey, $this->callbackArgumentCache)) {
            return $this->callbackArgumentCache[$cacheKey];
        }

        $reflection = new ReflectionFunction($callback);
        $functionParameters = $reflection->getParameters();
        $args = [];

        foreach ($functionParameters as $param) {
            $args[] = $param->getName();
        }

        $this->callbackArgumentCache[$cacheKey] = $args;

        return $args;
    }

    protected function resolveStatus(array $availableArgs): string
    {
        if ($this->successResolver === null) {
            return $availableArgs['exception'] === null ? 'success' : 'error';
        }

        $args = $this->resolveCallbackArguments($this->successResolver, $availableArgs);

        try {
            return ($this->successResolver)(...$args) ? 'success' : 'error';
        } catch (Throwable $e) {
            Log::warning('Success resolver for ' . $this->class . '::' . $this->method . ' threw: ' . $e->getMessage());

            return '__error__';
        }
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Attach a label whose value is computed per invocation.
     *
     * The callback is invoked after the hooked method returns. Its arguments
     * are auto-injected by **parameter name** (PHP named arguments) from a
     * fixed set of available bindings — declare only the ones you need:
     *
     * - `$instance`   — the object the hooked method was called on (or null for static)
     * - `$params`     — the arguments the method was called with (list)
     * - `$return`     — the value the method returned (mixed; null on void/throw)
     * - `$exception`  — the thrown Throwable, or null if the call succeeded
     *
     * Parameter **names** matter; types and order do not. Type hints are
     * ignored. The reflected signature is cached per closure instance.
     *
     * If the callback throws, the failure is logged and the label takes the
     * sentinel value `'__error__'` so dashboards can spot it.
     *
     * ```php
     * observe(Order::class, 'save')
     *     ->name('order_save_seconds')
     *     ->label('customer_id', fn ($instance) => $instance->customer_id)
     *     ->label('was_new',     fn ($return) => $return === true)
     *     ->label('error_class', fn ($exception) => $exception?->::class ?? 'none');
     * ```
     *
     * **Cardinality warning:** label values become dimensions in the time
     * series database. Keep them bounded — never user IDs, request IDs,
     * unbounded enums, or anything growing with traffic.
     */
    public function label(string $label, Closure $callback): self
    {
        $this->labels[$label] = $callback;

        return $this;
    }

    /**
     * Override the default success/error classification.
     *
     * By default an invocation is `success` when it didn't throw, `error`
     * when it did. Provide a resolver when that's too coarse — e.g. a method
     * that returns `false` on validation failure.
     *
     * The callback signature follows the same named-injection rules as
     * {@see label()}: declare any subset of `$instance`, `$params`, `$return`,
     * `$exception`. Return truthy for success, falsy for error.
     *
     * If the resolver itself throws, the `status` label takes the sentinel
     * value `'__error__'` — distinct from the operation's own success/error,
     * because we genuinely couldn't tell.
     *
     * ```php
     * observe(SomeJob::class, 'handle')
     *     ->name('some_job_seconds')
     *     ->successResolver(fn ($return, $exception) => $exception === null && $return !== false);
     * ```
     */
    public function successResolver(Closure $callback): self
    {
        $this->successResolver = $callback;

        return $this;
    }

    /**
     * Force-flush the OTel MeterProvider after each recording. Use for
     * long-running processes that don't go through Laravel's queue (e.g. AMQP
     * consumers, custom daemons) — without this, the SDK's ExportingReader
     * holds samples until the process dies.
     */
    public function flushAfter(bool $flush = true): self
    {
        $this->flushAfter = $flush;

        return $this;
    }
}
