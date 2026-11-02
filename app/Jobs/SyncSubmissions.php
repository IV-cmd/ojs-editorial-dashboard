<?php

namespace App\Jobs;

use App\Events\SyncRunStarted;
use App\Models\SyncRun;
use App\Services\Ojs\OjsClientInterface;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Coordinates a full sync: lists submissions, then fans out one detail job per
 * submission. Listing summaries is cheap; the per-submission detail fetch (which
 * carries the nested review assignments) is where the work and the failure risk
 * live, so each gets its own job and its own independent retry.
 *
 * Each attempt records a SyncRun. The detail jobs run as one batch that tolerates
 * individual failures; when the last one settles, the batch closes the run as
 * success or failure. Duration therefore covers queue wait and retries too.
 */
class SyncSubmissions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(OjsClientInterface $client): void
    {
        $run = SyncRun::start();
        SyncRunStarted::dispatch($run);

        try {
            $summaries = $client->getSubmissions();
        } catch (Throwable $e) {
            $run->recordFailure($e);
            $run->finish();

            throw $e;
        }

        $jobs = [];
        foreach ($summaries as $summary) {
            $id = $summary['id'] ?? null;

            if (is_numeric($id)) {
                $jobs[] = new IngestSubmission((int) $id, $run->id);
            }
        }

        $run->update(['submissions_listed' => count($jobs)]);

        if ($jobs === []) {
            $run->finish();

            return;
        }

        $runId = $run->id;

        try {
            Bus::batch($jobs)
                ->name("ojs-sync-{$runId}")
                ->allowFailures()
                ->finally(function (Batch $batch) use ($runId): void {
                    SyncRun::find($runId)?->finish($batch->failedJobs);
                })
                ->dispatch();
        } catch (Throwable $e) {
            // With a real queue this only fires if the queue store is unreachable. With the
            // sync queue a failing detail job throws here, and the batch's own transaction has
            // already rolled back that run's writes, so the run is closed as a failure.
            $run->recordFailure($e);
            $run->finish();

            throw $e;
        }
    }
}
