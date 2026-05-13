<?php

namespace motuslogistik\Metrics\Enums;

enum Type: string
{
    case Gauge = 'gauge';
    case Counter = 'counter';
}
