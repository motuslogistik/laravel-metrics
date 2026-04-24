<?php

namespace motuslogistik\Metrics\Http\Controllers;

use Illuminate\Http\Response;
use motuslogistik\Metrics\Exporters\PrometheusExporter;

class MetricsController
{
    public function __invoke(PrometheusExporter $exporter): Response
    {
        return new Response(
            $exporter->render(),
            200,
            ['Content-Type' => 'text/plain; version=0.0.4; charset=utf-8'],
        );
    }
}
