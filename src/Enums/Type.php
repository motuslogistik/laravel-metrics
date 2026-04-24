<?php

namespace motuslogistik\Metrics\Enums;

enum Type : string
{
    case Gauge = 'gauge';
    case Histogram = 'histogram';
    case Counter = 'counter';
}
