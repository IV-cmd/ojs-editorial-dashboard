<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Services\Ingestion\OjsUpserter;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function sampleDetail(array $overrides = []): array
{
    return array_merge([
        'id' => 2001,
        'contextId' => 7,
        'status' => 1,
        'stageId' => 3,
        'submissionProgress' => '',
        'currentPublicationId' => null,
        'dateSubmitted' => '2026-01-02 09:00:00',
        'dateLastActivity' => '2026-02-01 09:00:00',
        'lastModified' => '2026-02-01 09:00:00',
        'reviewAssignments' => [],
    ], $overrides);
}

it('maps an OJS submission detail onto a typed submission row', function () {
    $submission = app(OjsUpserter::class)->upsertSubmission(sampleDetail([
        'currentPublicationId' => 1303,
    ]));

    expect($submission->context_id)->toBe(7)
        ->and($submission->ojs_id)->toBe(2001)
        ->and($submission->status)->toBe(1)
        ->and($submission->stage_id)->toBe(3)
        ->and($submission->current_publication_id)->toBe(1303)
        ->and($submission->date_submitted)->toBeInstanceOf(CarbonImmutable::class)
        ->and(Submission::count())->toBe(1);
});

it('maps nested review assignments including booleans and nullable dates', function () {
    app(OjsUpserter::class)->upsertSubmission(sampleDetail([
        'reviewAssignments' => [
            [
                'id' => 9001,
                'reviewerId' => 55,
                'round' => 1,
                'reviewRoundId' => 3,
                'status' => 4,
                'dateAssigned' => '2026-03-01 09:00:00',
                'dateDue' => '2026-03-15 09:00:00',
                'dateResponseDue' => '2026-03-10 09:00:00',
                'dateConfirmed' => null,
                'dateCompleted' => null,
                'declined' => true,
                'cancelled' => false,
            ],
        ],
    ]));

    $assignment = ReviewAssignment::sole();

    expect($assignment->ojs_id)->toBe(9001)
        ->and($assignment->reviewer_id)->toBe(55)
        ->and($assignment->status)->toBe(4)
        ->and($assignment->date_due)->toBeInstanceOf(CarbonImmutable::class)
        ->and($assignment->date_confirmed)->toBeNull()
        ->and($assignment->declined)->toBeTrue()
        ->and($assignment->cancelled)->toBeFalse()
        ->and($assignment->submission->ojs_id)->toBe(2001);
});

it('updates existing rows instead of duplicating when the same detail is upserted twice', function () {
    $detail = sampleDetail([
        'status' => 1,
        'reviewAssignments' => [[
            'id' => 9001,
            'round' => 1,
            'status' => 4,
            'dateDue' => '2026-03-15 09:00:00',
        ]],
    ]);

    app(OjsUpserter::class)->upsertSubmission($detail);

    $detail['status'] = 3;
    $detail['reviewAssignments'][0]['status'] = 6;
    app(OjsUpserter::class)->upsertSubmission($detail);

    expect(Submission::count())->toBe(1)
        ->and(ReviewAssignment::count())->toBe(1)
        ->and(Submission::sole()->status)->toBe(3)
        ->and(ReviewAssignment::sole()->status)->toBe(6);
});

it('rolls back the whole submission when a nested review assignment is invalid', function () {
    $detail = sampleDetail([
        'id' => 2002,
        'reviewAssignments' => [
            ['id' => 9100, 'round' => 1, 'status' => 4],
            ['round' => 1, 'status' => 4], // missing OJS id -> NOT NULL violation
        ],
    ]);

    expect(fn () => app(OjsUpserter::class)->upsertSubmission($detail))
        ->toThrow(QueryException::class);

    expect(Submission::where('ojs_id', 2002)->exists())->toBeFalse()
        ->and(ReviewAssignment::where('ojs_id', 9100)->exists())->toBeFalse();
});
