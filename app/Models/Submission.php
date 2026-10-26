<?php

namespace App\Models;

use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'context_id',
        'ojs_id',
        'status',
        'stage_id',
        'submission_progress',
        'current_publication_id',
        'date_submitted',
        'date_last_activity',
        'last_modified',
    ];

    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'ojs_id' => 'integer',
            'status' => 'integer',
            'stage_id' => 'integer',
            'current_publication_id' => 'integer',
            'date_submitted' => 'immutable_datetime',
            'date_last_activity' => 'immutable_datetime',
            'last_modified' => 'immutable_datetime',
        ];
    }

    /**
     * @return HasMany<ReviewAssignment, $this>
     */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }
}
