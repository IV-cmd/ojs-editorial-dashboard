<?php

namespace App\Jobs;

use App\Services\Ojs\OjsClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Coordinates a full sync: lists submissions, then fans out one detail job per
 * submission. Listing summaries is cheap; the per-submission detail fetch (which
 * carries the nested review assignments) is where the work and the failure risk
 * live, so each gets its own job and its own independent retry.
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
        foreach ($client->getSubmissions() as $summary) {
            $id = $summary['id'] ?? null;

            if (is_numeric($id)) {
                IngestSubmission::dispatch((int) $id);
            }
        }
    }
}
