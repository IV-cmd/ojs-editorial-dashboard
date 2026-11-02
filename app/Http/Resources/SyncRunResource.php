<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\SyncRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SyncRun
 */
class SyncRunResource extends JsonResource
{
    use FormatsDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'started_at' => self::isoDate($this->started_at),
            'finished_at' => self::isoDate($this->finished_at),
            'duration_seconds' => $this->durationSeconds(),
            'submissions_listed' => $this->submissions_listed,
            'submissions_upserted' => $this->submissions_upserted,
            'review_assignments_upserted' => $this->review_assignments_upserted,
            'failures' => [
                'api_error' => $this->failures_api_error,
                'validation' => $this->failures_validation,
                'db' => $this->failures_db,
                'other' => $this->failures_other,
            ],
            // Error text can quote upstream response bodies, so only editors get it.
            'error_excerpt' => $this->when(
                (bool) $request->user()?->can('view-submissions'),
                fn () => $this->error_excerpt,
            ),
        ];
    }
}
