<?php

namespace App\Jobs;

use App\Services\Ingestion\OjsUpserter;
use App\Services\Ojs\OjsClientInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fetches one submission's detail and persists it with its review assignments.
 *
 * It carries only the OJS submission ID, not the payload, so a retry always
 * re-fetches current data. A transport failure surfaces as OjsApiException and
 * fails the job for retry rather than writing partial state.
 */
class IngestSubmission implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $submissionId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(OjsClientInterface $client, OjsUpserter $upserter): void
    {
        $detail = $client->getSubmission($this->submissionId);

        $upserter->upsertSubmission($detail);
    }
}
