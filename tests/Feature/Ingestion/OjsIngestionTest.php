<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('ingests every mock submission and its review assignments', function () {
    Artisan::call('ojs:sync');

    expect(Submission::count())->toBe(5)
        ->and(ReviewAssignment::count())->toBe(3)
        ->and(Submission::where('ojs_id', 105)->sole()->reviewAssignments)->toHaveCount(3);
});

it('produces identical database state when the sync runs twice', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');

    Artisan::call('ojs:sync');
    $submissionIds = Submission::orderBy('id')->pluck('id')->all();
    $assignmentIds = ReviewAssignment::orderBy('id')->pluck('id')->all();

    Artisan::call('ojs:sync');

    expect(Submission::count())->toBe(5)
        ->and(ReviewAssignment::count())->toBe(3)
        ->and(Submission::orderBy('id')->pluck('id')->all())->toBe($submissionIds)
        ->and(ReviewAssignment::orderBy('id')->pluck('id')->all())->toBe($assignmentIds);
});
