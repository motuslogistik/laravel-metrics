<?php

namespace motuslogistik\Metrics;

class Metrics
{
    public static function prefix(): string
    {
        return config('metrics.prefix', 'metrics|');
    }
}
