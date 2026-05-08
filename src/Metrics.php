<?php

namespace motuslogistik\Metrics;

use motuslogistik\Metrics\Contracts\Store;

class Metrics
{
    public const GLOBAL_STORE = Store::class.':global';

    public static function prefix(): string
    {
        return config('metrics.prefix', 'metrics|');
    }

    public static function store(): Store
    {
        return app(Store::class);
    }

    public static function globalStore(): ?Store
    {
        if (! app()->bound(self::GLOBAL_STORE)) {
            return null;
        }

        return app(self::GLOBAL_STORE);
    }

    /**
     * @return list<Store>
     */
    public static function stores(): array
    {
        $stores = [self::store()];

        $global = self::globalStore();
        if ($global !== null) {
            $stores[] = $global;
        }

        return $stores;
    }
}
