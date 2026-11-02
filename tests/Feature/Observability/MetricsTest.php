<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Services\Dashboard\EditorialQueries;
use App\Services\Metrics\PrometheusMetrics;
use App\Services\Ojs\OjsApiException;
use App\Services\Ojs\OjsClientInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function metricValue(string $exposition, string $series): ?float
{
    return preg_match('/^'.preg_quote($series, '/').' (\S+)$/m', $exposition, $m) ? (float) $m[1] : null;
}

it('serves every metric in Prometheus text format after a sync', function () {
    Artisan::call('ojs:sync');

    $response = $this->get('/metrics')->assertOk();
    $body = $response->getContent();

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
    foreach ([
        'ojs_sync_duration_seconds_count', 'ojs_sync_total', 'ojs_upserted_total', 'ojs_sync_failures_total',
        'ojs_sync_last_success_timestamp', 'ojs_submissions_stuck', 'ojs_reviews_overdue',
    ] as $name) {
        expect($body)->toContain($name);
    }

    expect(metricValue($body, 'ojs_sync_total{result="success"}'))->toBe(1.0)
        ->and(metricValue($body, 'ojs_upserted_total{entity="submission"}'))->toBe(5.0)
        ->and(metricValue($body, 'ojs_upserted_total{entity="review_assignment"}'))->toBe(3.0)
        ->and(metricValue($body, 'ojs_sync_duration_seconds_count'))->toBe(1.0)
        ->and(metricValue($body, 'ojs_sync_last_success_timestamp'))->toBeGreaterThan(0.0);
});

it('counts a failed sync by result and reason', function () {
    app()->instance(OjsClientInterface::class, failingOjsClient(failListing: true));

    expect(fn () => Artisan::call('ojs:sync'))->toThrow(OjsApiException::class);

    $body = $this->get('/metrics')->assertOk()->getContent();

    expect(metricValue($body, 'ojs_sync_total{result="failure"}'))->toBe(1.0)
        ->and(metricValue($body, 'ojs_sync_total{result="success"}'))->toBe(0.0)
        ->and(metricValue($body, 'ojs_sync_failures_total{reason="api_error"}'))->toBe(1.0)
        ->and(metricValue($body, 'ojs_sync_failures_total{reason="db"}'))->toBe(0.0)
        ->and(metricValue($body, 'ojs_sync_last_success_timestamp'))->toBe(0.0);
});

it('reports stuck and overdue gauges from EditorialQueries at the given time', function () {
    $asOf = CarbonImmutable::parse('2026-03-15 12:00:00');
    $stuckDays = app(EditorialQueries::class)->stuckDays();

    Submission::factory()->queued()->create(['date_last_activity' => $asOf->subDays($stuckDays)]);
    Submission::factory()->queued()->create(['date_last_activity' => $asOf->subDays($stuckDays)->addSecond()]);
    ReviewAssignment::factory()->create(['date_due' => $asOf->subDay()]);
    ReviewAssignment::factory()->create(['date_due' => $asOf->startOfDay()]);

    $body = app(PrometheusMetrics::class)->render($asOf);
    $queries = app(EditorialQueries::class);

    expect(metricValue($body, 'ojs_submissions_stuck'))->toBe((float) $queries->stuckSubmissions($asOf)->count())
        ->and(metricValue($body, 'ojs_submissions_stuck'))->toBe(1.0)
        ->and(metricValue($body, 'ojs_reviews_overdue'))->toBe((float) $queries->overdueReviews($asOf)->count())
        ->and(metricValue($body, 'ojs_reviews_overdue'))->toBe(1.0);
});
