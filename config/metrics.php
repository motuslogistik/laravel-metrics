<?php

// config for motuslogistik/Metrics

return [
    /*
     | Instrumentation scope name passed to the OTel MeterProvider. Surfaces
     | in exported metrics as `otel.scope.name`.
     */
    'meter_name' => 'motuslogistik/metrics',
];
