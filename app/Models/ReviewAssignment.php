<?php

namespace App\Models;

use Database\Factories\ReviewAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewAssignment extends Model
{
    /** @use HasFactory<ReviewAssignmentFactory> */
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'ojs_id',
        'reviewer_id',
        'round',
        'review_round_id',
        'status',
        'date_assigned',
        'date_due',
        'date_response_due',
        'date_confirmed',
        'date_completed',
        'declined',
        'cancelled',
    ];

    protected function casts(): array
    {
        return [
            'submission_id' => 'integer',
            'ojs_id' => 'integer',
            'reviewer_id' => 'integer',
            'round' => 'integer',
            'review_round_id' => 'integer',
            'status' => 'integer',
            'date_assigned' => 'immutable_datetime',
            'date_due' => 'immutable_datetime',
            'date_response_due' => 'immutable_datetime',
            'date_confirmed' => 'immutable_datetime',
            'date_completed' => 'immutable_datetime',
            'declined' => 'boolean',
            'cancelled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Submission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }
}
