<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('relates submissions to review assignments in both directions', function () {
    $submission = Submission::factory()
        ->has(ReviewAssignment::factory()->count(3), 'reviewAssignments')
        ->create();

    expect($submission->reviewAssignments)->toHaveCount(3);

    foreach ($submission->reviewAssignments as $assignment) {
        expect($assignment->submission->is($submission))->toBeTrue();
    }
});

it('casts persistence fields to their declared types', function () {
    $submission = Submission::factory()->create([
        'context_id' => 71,
        'ojs_id' => 501,
        'status' => 3,
        'stage_id' => 5,
        'current_publication_id' => 1501,
        'date_submitted' => '2025-01-02 03:04:05',
        'date_last_activity' => '2025-01-03 04:05:06',
        'last_modified' => '2025-01-04 05:06:07',
    ]);

    $assignment = ReviewAssignment::factory()->for($submission)->create([
        'ojs_id' => 601,
        'reviewer_id' => 701,
        'round' => 2,
        'review_round_id' => 801,
        'status' => 6,
        'date_assigned' => '2025-02-01 03:04:05',
        'date_due' => '2025-02-02 04:05:06',
        'date_response_due' => '2025-02-03 05:06:07',
        'date_confirmed' => '2025-02-04 06:07:08',
        'date_completed' => '2025-02-05 07:08:09',
        'declined' => true,
        'cancelled' => false,
    ]);

    expect($submission->id)->toBeInt()
        ->and($submission->context_id)->toBeInt()
        ->and($submission->ojs_id)->toBeInt()
        ->and($submission->status)->toBeInt()
        ->and($submission->stage_id)->toBeInt()
        ->and($submission->current_publication_id)->toBeInt()
        ->and($submission->date_submitted)->toBeInstanceOf(CarbonImmutable::class)
        ->and($submission->date_last_activity)->toBeInstanceOf(CarbonImmutable::class)
        ->and($submission->last_modified)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->id)->toBeInt()
        ->and($assignment->submission_id)->toBeInt()
        ->and($assignment->ojs_id)->toBeInt()
        ->and($assignment->reviewer_id)->toBeInt()
        ->and($assignment->round)->toBeInt()
        ->and($assignment->review_round_id)->toBeInt()
        ->and($assignment->status)->toBeInt()
        ->and($assignment->date_assigned)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->date_due)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->date_response_due)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->date_confirmed)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->date_completed)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->declined)->toBeTrue()
        ->and($assignment->cancelled)->toBeFalse();
});

it('enforces the submission context and OJS ID unique key in MySQL', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');

    Submission::factory()->create([
        'context_id' => 71,
        'ojs_id' => 501,
    ]);

    expect(fn () => Submission::factory()->create([
        'context_id' => 71,
        'ojs_id' => 501,
    ]))->toThrow(QueryException::class);
});

it('enforces the review assignment OJS ID unique key in MySQL', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');

    $submission = Submission::factory()->create();
    ReviewAssignment::factory()->for($submission)->create([
        'ojs_id' => 601,
    ]);

    expect(fn () => ReviewAssignment::factory()->for($submission)->create([
        'ojs_id' => 601,
    ]))->toThrow(QueryException::class);
});
