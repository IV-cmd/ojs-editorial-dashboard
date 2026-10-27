<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Submission
 */
class SubmissionResource extends JsonResource
{
    use FormatsDates;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ojs_id' => $this->ojs_id,
            'context_id' => $this->context_id,
            'status' => $this->status,
            'stage_id' => $this->stage_id,
            'submission_progress' => $this->submission_progress,
            'current_publication_id' => $this->current_publication_id,
            'date_submitted' => self::isoDate($this->date_submitted),
            'date_last_activity' => self::isoDate($this->date_last_activity),
            'last_modified' => self::isoDate($this->last_modified),
            'review_assignments_count' => $this->whenCounted('reviewAssignments'),
            'review_assignments' => ReviewAssignmentResource::collection($this->whenLoaded('reviewAssignments')),
        ];
    }
}
