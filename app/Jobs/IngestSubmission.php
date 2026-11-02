<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Services\Ingestion\OjsUpserter;
use App\Services\Ojs\OjsClientInterface;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Fetches one submission's detail and persists it with its review assignments.
 *
 * It carries only the OJS submission ID, not the payload, so a retry always
 * re-fetches current data. A transport failure surfaces as OjsApiException and
 * fails the job for retry rather than writing partial state.
 */
class IngestSubmission implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $submissionId,
        public readonly ?int $syncRunId = null,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(OjsClientInterface $client, OjsUpserter $upserter): void
    {
        try {
            $detail = $client->getSubmission($this->submissionId);
            $upserter->upsertSubmission($detail);
        } catch (Throwable $e) {
            // Counted here, on the last attempt, rather than in failed(): the queue closes the
            // batch (and so the run) before it calls failed(), which would be too late.
            if ($this->syncRunId !== null && $this->attempts() >= $this->tries) {
                SyncRun::find($this->syncRunId)?->recordFailure($e);
            }

            throw $e;
        }

        if ($this->syncRunId !== null) {
            $assignments = $detail['reviewAssignments'] ?? [];
            SyncRun::query()->whereKey($this->syncRunId)->incrementEach([
                'submissions_upserted' => 1,
                'review_assignments_upserted' => is_array($assignments) ? count(array_filter($assignments, 'is_array')) : 0,
            ]);
        }
    }
}
