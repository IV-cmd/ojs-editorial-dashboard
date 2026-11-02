<?php

namespace App\Services\Metrics;

use App\Models\SyncRun;
use App\Services\Dashboard\EditorialQueries;
use App\Services\Ingestion\FailureReason;
use Carbon\CarbonImmutable;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory;

/**
 * Builds the Prometheus exposition at scrape time.
 *
 * Storage choice: an InMemory registry rebuilt on every scrape from the sync_runs table
 * and EditorialQueries. Ingestion runs in queue worker processes and /metrics is served by
 * the web process; APCu memory is not shared between them, so counters incremented by a
 * worker would always read zero here. Redis would share them but adds a service, and its
 * counters vanish when it restarts. The database already holds every run, so counters
 * derived from it are shared across processes and survive restarts. The cost is one scan
 * of sync_runs per scrape, which grows by one row per run.
 */
final class PrometheusMetrics
{
    /** Seconds: from a mock run (well under a second) to a slow live sync with retries. */
    private const DURATION_BUCKETS = [0.5, 1, 5, 15, 30, 60, 120, 300, 600, 1800];

    public function __construct(private readonly EditorialQueries $queries) {}

    public function render(CarbonImmutable $asOf): string
    {
        $registry = new CollectorRegistry(new InMemory, false);

        $this->pipelineMetrics($registry);
        $this->stateGauges($registry, $asOf);

        return (new RenderTextFormat)->render($registry->getMetricFamilySamples());
    }

    private function pipelineMetrics(CollectorRegistry $registry): void
    {
        $duration = $registry->registerHistogram(
            'ojs', 'sync_duration_seconds',
            'Wall time of finished ojs:sync runs, from start until the last detail job settled (includes queue wait and retries).',
            [], self::DURATION_BUCKETS,
        );
        foreach (SyncRun::query()->whereNotNull('finished_at')->select(['id', 'started_at', 'finished_at'])->lazyById() as $run) {
            $duration->observe((float) $run->durationSeconds());
        }

        $runs = $registry->registerCounter('ojs', 'sync_total', 'Finished ojs:sync runs by result.', ['result']);
        foreach ([SyncRun::SUCCESS, SyncRun::FAILURE] as $result) {
            $runs->incBy(SyncRun::query()->where('status', $result)->count(), [$result]);
        }

        $upserted = $registry->registerCounter('ojs', 'upserted_total', 'Rows written by ingestion, by entity.', ['entity']);
        $upserted->incBy((int) SyncRun::query()->sum('submissions_upserted'), ['submission']);
        $upserted->incBy((int) SyncRun::query()->sum('review_assignments_upserted'), ['review_assignment']);

        $failures = $registry->registerCounter('ojs', 'sync_failures_total', 'Ingestion failures by reason (a detail job counts once, after its retries).', ['reason']);
        foreach (FailureReason::ALL as $reason) {
            $failures->incBy((int) SyncRun::query()->sum("failures_{$reason}"), [$reason]);
        }

        $lastSuccess = SyncRun::query()->where('status', SyncRun::SUCCESS)->max('finished_at');
        $registry->registerGauge('ojs', 'sync_last_success_timestamp', 'Unix time of the last successful sync; 0 if none has succeeded.')
            ->set($lastSuccess === null ? 0 : CarbonImmutable::parse($lastSuccess)->getTimestamp());
    }

    private function stateGauges(CollectorRegistry $registry, CarbonImmutable $asOf): void
    {
        $registry->registerGauge('ojs', 'submissions_stuck', 'Submissions the dashboard currently counts as stuck.')
            ->set($this->queries->stuckSubmissions($asOf)->count());

        $registry->registerGauge('ojs', 'reviews_overdue', 'Review assignments the dashboard currently counts as overdue.')
            ->set($this->queries->overdueReviews($asOf)->count());
    }
}
