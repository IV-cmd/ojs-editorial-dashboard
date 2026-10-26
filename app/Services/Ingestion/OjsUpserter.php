<?php

namespace App\Services\Ingestion;

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;

/**
 * Maps decoded OJS payloads onto local rows.
 *
 * Takes already-decoded arrays and writes rows; it performs no HTTP and knows
 * nothing about queues, so it can be exercised directly in tests. Every write
 * keys on the natural OJS identifiers that the schema marks unique, which is
 * what makes a repeated run converge on the same state instead of duplicating.
 */
class OjsUpserter
{
    /**
     * Upsert one submission and the review assignments nested in its detail payload.
     *
     * The submission and its assignments share a single transaction: a payload
     * that fails part-way leaves no half-written submission behind.
     *
     * @param  array<string, mixed>  $detail
     */
    public function upsertSubmission(array $detail): Submission
    {
        return DB::transaction(function () use ($detail): Submission {
            $submission = Submission::updateOrCreate(
                [
                    'context_id' => self::toInt($detail['contextId'] ?? null),
                    'ojs_id' => self::toInt($detail['id'] ?? null),
                ],
                [
                    'status' => self::toInt($detail['status'] ?? null),
                    'stage_id' => self::toInt($detail['stageId'] ?? null),
                    'submission_progress' => self::toText($detail['submissionProgress'] ?? null),
                    'current_publication_id' => self::toInt($detail['currentPublicationId'] ?? null),
                    'date_submitted' => self::toDate($detail['dateSubmitted'] ?? null),
                    'date_last_activity' => self::toDate($detail['dateLastActivity'] ?? null),
                    'last_modified' => self::toDate($detail['lastModified'] ?? null),
                ],
            );

            $assignments = $detail['reviewAssignments'] ?? [];

            if (is_array($assignments)) {
                foreach ($assignments as $assignment) {
                    if (is_array($assignment)) {
                        $this->upsertReviewAssignment($submission, $assignment);
                    }
                }
            }

            return $submission;
        });
    }

    /**
     * A review assignment ID is unique across an OJS install, so it alone keys the row.
     *
     * Reviewer names and other reviewer personal fields are deliberately not stored;
     * only the numeric reviewer ID is kept, so the dashboard can group work without
     * holding reviewer identities.
     *
     * @param  array<string, mixed>  $assignment
     */
    private function upsertReviewAssignment(Submission $submission, array $assignment): void
    {
        ReviewAssignment::updateOrCreate(
            ['ojs_id' => self::toInt($assignment['id'] ?? null)],
            [
                'submission_id' => $submission->id,
                'reviewer_id' => self::toInt($assignment['reviewerId'] ?? null),
                'round' => self::toInt($assignment['round'] ?? null),
                'review_round_id' => self::toInt($assignment['reviewRoundId'] ?? null),
                'status' => self::toInt($assignment['status'] ?? null),
                'date_assigned' => self::toDate($assignment['dateAssigned'] ?? null),
                'date_due' => self::toDate($assignment['dateDue'] ?? null),
                'date_response_due' => self::toDate($assignment['dateResponseDue'] ?? null),
                'date_confirmed' => self::toDate($assignment['dateConfirmed'] ?? null),
                'date_completed' => self::toDate($assignment['dateCompleted'] ?? null),
                'declined' => self::toFlag($assignment['declined'] ?? null),
                'cancelled' => self::toFlag($assignment['cancelled'] ?? null),
            ],
        );
    }

    private static function toInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private static function toText(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    // OJS sends timestamps as strings; an empty string means "unset", not epoch.
    private static function toDate(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function toFlag(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
