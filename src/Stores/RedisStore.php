<?php

namespace motuslogistik\Metrics\Stores;

use Generator;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use motuslogistik\Metrics\Contracts\Store;

class RedisStore implements Store
{
    private Connection $redis;

    public function __construct(?string $connection = null)
    {
        $this->redis = Redis::connection($connection);
    }

    public function incr(string $key, int|float $amount = 1)
    {
        if (is_int($amount)) {
            $this->redis->incrby($key, $amount);

            return;
        }

        $this->redis->incrbyfloat($key, $amount);
    }

    public function decr(string $key, int|float $amount = 1)
    {
        if (is_int($amount)) {
            $this->redis->decrby($key, $amount);

            return;
        }

        $this->redis->incrbyfloat($key, -$amount);
    }

    public function get(string $key)
    {
        $value = $this->redis->get($key);

        return $value === null ? false : $value;
    }

    public function set(string $key, $value)
    {
        $this->redis->set($key, $value);
    }

    public function has(string $key)
    {
        return (bool) $this->redis->exists($key);
    }

    public function clear(string $key)
    {
        $this->redis->del($key);
    }

    public function iterator(?string $prefix = null): Generator
    {
        $pattern = $prefix === null ? '*' : $this->escapeGlob($prefix).'*';
        $keys = $this->redis->keys($pattern);

        if (empty($keys)) {
            return;
        }

        // Redis::keys() returns keys with the connection prefix already included,
        // but subsequent commands re-apply it. Strip it to avoid double-prefixing.
        $connectionPrefix = $this->getConnectionPrefix();
        if ($connectionPrefix !== '') {
            $keys = array_map(
                fn ($key) => str_starts_with($key, $connectionPrefix)
                    ? substr($key, strlen($connectionPrefix))
                    : $key,
                $keys
            );
        }

        $values = $this->redis->mget($keys);

        foreach ($keys as $i => $key) {
            $value = $values[$i] ?? null;
            if ($value === null || $value === false) {
                continue;
            }

            yield $key => $value;
        }
    }

    private function getConnectionPrefix(): string
    {
        $client = $this->redis->client();

        if (method_exists($client, 'getOption') && defined('Redis::OPT_PREFIX')) {
            return (string) $client->getOption(\Redis::OPT_PREFIX);
        }

        if (method_exists($client, 'getOptions')) {
            $options = $client->getOptions();

            return (string) ($options->prefix ?? '');
        }

        return '';
    }

    private function escapeGlob(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            '*' => '\\*',
            '?' => '\\?',
            '[' => '\\[',
            ']' => '\\]',
        ]);
    }
}
