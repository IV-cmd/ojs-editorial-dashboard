<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// The mock fixtures carry real dates, so the clock is pinned to keep "stuck" and "overdue" stable.
beforeEach(function () {
    $this->travelTo('2026-10-27 12:00:00');
    Artisan::call('ojs:sync');
});

function actingAsEditor(): User
{
    return Sanctum::actingAs(User::factory()->editor()->create());
}

function actingAsViewer(): User
{
    return Sanctum::actingAs(User::factory()->create());
}

dataset('protected endpoints', [
    '/api/submissions',
    '/api/submissions/1',
    '/api/dashboard/summary',
    '/api/dashboard/stuck',
    '/api/dashboard/overdue-reviews',
]);

it('rejects unauthenticated requests with a JSON 401', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized()->assertJsonPath('message', 'Unauthenticated.');
    $this->get($uri)->assertUnauthorized();
})->with('protected endpoints');

it('forbids viewers from raw submission records', function () {
    actingAsViewer();

    $this->getJson('/api/submissions')->assertForbidden();
    $this->getJson('/api/submissions/'.Submission::where('ojs_id', 105)->value('id'))->assertForbidden();
});

it('lists ingested submissions for editors with review assignment counts', function () {
    actingAsEditor();

    $this->getJson('/api/submissions')
        ->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('data.4.ojs_id', 105)
        ->assertJsonPath('data.4.review_assignments_count', 3)
        ->assertJsonStructure(['data' => [['id', 'ojs_id', 'context_id', 'status', 'stage_id', 'date_last_activity']], 'links', 'meta']);
});

it('paginates the submission list', function () {
    actingAsEditor();

    $this->getJson('/api/submissions?per_page=2&page=3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('data.0.ojs_id', 105);

    $this->getJson('/api/submissions?per_page=101')->assertUnprocessable();
});

it('filters the submission list by status and context', function () {
    actingAsEditor();

    expect(collect($this->getJson('/api/submissions?status=1')->json('data'))->pluck('ojs_id')->all())->toBe([101, 102, 105]);
    $this->getJson('/api/submissions?context_id=2')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/submissions?status=abc')->assertUnprocessable();
});

it('shows a submission with its review assignments to editors', function () {
    actingAsEditor();
    ReviewAssignment::where('ojs_id', 501)->update(['reviewer_id' => 77]);

    $response = $this->getJson('/api/submissions/'.Submission::where('ojs_id', 105)->value('id'))
        ->assertOk()
        ->assertJsonPath('data.ojs_id', 105)
        ->assertJsonCount(3, 'data.review_assignments')
        ->assertJsonPath('data.review_assignments.0.ojs_id', 501)
        ->assertJsonPath('data.review_assignments.0.reviewer_id', 77);

    $this->getJson('/api/submissions/999999')->assertNotFound();
});

it('lists stuck submissions with the threshold used', function () {
    actingAsViewer();

    $this->getJson('/api/dashboard/stuck')
        ->assertOk()
        ->assertJsonPath('meta.stuck_threshold_days', 14)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.ojs_id', 101);
});

it('shows overdue reviews with reviewer identity for editors only', function () {
    ReviewAssignment::where('ojs_id', 501)->update(['reviewer_id' => 77]);

    actingAsEditor();
    $editorView = $this->getJson('/api/dashboard/overdue-reviews')->assertOk()->json('data');

    expect(collect($editorView)->pluck('ojs_id')->all())->toBe([501, 502])
        ->and($editorView[0]['reviewer_id'])->toBe(77)
        ->and($editorView[0]['submission_ojs_id'])->toBe(105);

    actingAsViewer();
    $viewerView = $this->getJson('/api/dashboard/overdue-reviews')->assertOk()->json('data');

    expect(collect($viewerView)->pluck('ojs_id')->all())->toBe([501, 502]);
    foreach ($viewerView as $assignment) {
        expect($assignment)->not->toHaveKey('reviewer_id');
    }
});

it('returns summary counts backed by the ingested data', function () {
    actingAsViewer();

    $this->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertExactJson([
            'data' => [
                'submissions_total' => 5,
                'submissions_by_status' => [
                    ['status' => 1, 'count' => 3],
                    ['status' => 3, 'count' => 1],
                    ['status' => 4, 'count' => 1],
                ],
                'stuck_count' => 1,
                'overdue_reviews_count' => 2,
            ],
            'meta' => ['as_of' => '2026-10-27T12:00:00+00:00', 'stuck_threshold_days' => 14],
        ]);
});
