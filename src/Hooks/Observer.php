<?php

declare(strict_types=1);

namespace motuslogistik\Metrics\Hooks;

use Closure;
use Illuminate\Support\Facades\Log;
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

    public function label(string $label, Closure $callback): self
    {
        $this->labels[$label] = $callback;

        return $this;
    }

    public function successResolver(Closure $callback): self
    {
        $this->successResolver = $callback;

        return $this;
    }
}
