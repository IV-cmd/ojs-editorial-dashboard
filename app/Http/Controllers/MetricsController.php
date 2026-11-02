<?php

namespace App\Http\Controllers;

use App\Services\Metrics\PrometheusMetrics;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Prometheus\RenderTextFormat;

// The clock is read here and handed to the metrics, the same way the API controllers do it.
class MetricsController extends Controller
{
    public function __invoke(PrometheusMetrics $metrics): Response
    {
        return response($metrics->render(CarbonImmutable::now()), 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
