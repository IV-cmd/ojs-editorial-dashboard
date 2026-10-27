<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\FormatsDates;
use App\Models\ReviewAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReviewAssignment
 */
class ReviewAssignmentResource extends JsonResource
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
            'submission_id' => $this->submission_id,
            'submission_ojs_id' => $this->whenLoaded('submission', fn () => $this->submission->ojs_id),
            // Omitted entirely (not nulled) for roles that may not see who is reviewing.
            'reviewer_id' => $this->when(
                (bool) $request->user()?->can('view-reviewer-identity'),
                fn () => $this->reviewer_id,
            ),
            'round' => $this->round,
            'review_round_id' => $this->review_round_id,
            'status' => $this->status,
            'date_assigned' => self::isoDate($this->date_assigned),
            'date_due' => self::isoDate($this->date_due),
            'date_response_due' => self::isoDate($this->date_response_due),
            'date_confirmed' => self::isoDate($this->date_confirmed),
            'date_completed' => self::isoDate($this->date_completed),
            'declined' => $this->declined,
            'cancelled' => $this->cancelled,
        ];
    }
}
