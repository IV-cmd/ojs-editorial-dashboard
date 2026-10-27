<?php

use App\Models\ReviewAssignment;
use App\Models\Submission;
use App\Services\Dashboard\EditorialQueries;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The rules under test take the reference time as an argument, so no clock freezing is needed.
beforeEach(function () {
    $this->asOf = CarbonImmutable::parse('2026-06-15 12:00:00', 'UTC');
    $this->queries = new EditorialQueries(14);
});

function queuedIdleSince(CarbonImmutable|string|null $lastActivity, int $ojsId): Submission
{
    return Submission::factory()->queued()->create([
        'ojs_id' => $ojsId,
        'date_last_activity' => $lastActivity,
    ]);
}

function reviewDue(string $due, array $overrides = []): ReviewAssignment
{
    return ReviewAssignment::factory()->create(array_merge([
        'date_due' => $due,
        'date_completed' => null,
        'declined' => false,
        'cancelled' => false,
    ], $overrides));
}

it('counts a queued submission idle for exactly the threshold as stuck', function () {
    queuedIdleSince($this->asOf->subDays(14), 1);

    expect($this->queries->stuckSubmissions($this->asOf)->pluck('ojs_id')->all())->toBe([1]);
});

it('does not count a submission one second short of the threshold as stuck', function () {
    queuedIdleSince($this->asOf->subDays(14)->addSecond(), 1);

    expect($this->queries->stuckSubmissions($this->asOf)->count())->toBe(0);
});

it('only counts submissions still in the workflow as stuck', function () {
    queuedIdleSince($this->asOf->subDays(90), 1);
    Submission::factory()->published()->create(['ojs_id' => 2, 'date_last_activity' => $this->asOf->subDays(90)]);
    Submission::factory()->declined()->create(['ojs_id' => 3, 'date_last_activity' => $this->asOf->subDays(90)]);

    expect($this->queries->stuckSubmissions($this->asOf)->pluck('ojs_id')->all())->toBe([1]);
});

it('leaves out submissions with no activity date', function () {
    queuedIdleSince(null, 1);

    expect($this->queries->stuckSubmissions($this->asOf)->count())->toBe(0);
});

it('uses the configured threshold rather than a fixed one', function () {
    queuedIdleSince($this->asOf->subDays(10), 1);

    expect((new EditorialQueries(14))->stuckSubmissions($this->asOf)->count())->toBe(0)
        ->and((new EditorialQueries(7))->stuckSubmissions($this->asOf)->count())->toBe(1);

    config(['dashboard.stuck_days' => 7]);
    expect(app(EditorialQueries::class)->stuckDays())->toBe(7);
});

it('counts a review due before today and not completed as overdue', function () {
    $late = reviewDue('2026-06-14 23:59:59');

    expect($this->queries->overdueReviews($this->asOf)->pluck('id')->all())->toBe([$late->id]);
});

it('does not count a review due today as overdue, even after its due time', function () {
    reviewDue('2026-06-15 00:00:00');
    reviewDue('2026-06-15 09:00:00');

    expect($this->queries->overdueReviews($this->asOf)->count())->toBe(0);
});

it('does not count a review completed after its due date as overdue', function () {
    reviewDue('2026-06-01 09:00:00', ['date_completed' => '2026-06-10 09:00:00']);

    expect($this->queries->overdueReviews($this->asOf)->count())->toBe(0);
});

it('does not count declined, cancelled or undated reviews as overdue', function () {
    reviewDue('2026-06-01 09:00:00', ['declined' => true]);
    reviewDue('2026-06-01 09:00:00', ['cancelled' => true]);
    ReviewAssignment::factory()->create(['date_due' => null, 'date_completed' => null]);

    expect($this->queries->overdueReviews($this->asOf)->count())->toBe(0);
});

it('summarises counts from the same rules the lists use', function () {
    queuedIdleSince($this->asOf->subDays(30), 1);
    queuedIdleSince($this->asOf->subDay(), 2);
    Submission::factory()->published()->create(['ojs_id' => 3]);
    reviewDue('2026-06-01 09:00:00', ['submission_id' => Submission::where('ojs_id', 2)->value('id')]);

    expect($this->queries->summary($this->asOf))->toBe([
        'submissions_total' => 3,
        'submissions_by_status' => [
            ['status' => 1, 'count' => 2],
            ['status' => 3, 'count' => 1],
        ],
        'stuck_count' => 1,
        'overdue_reviews_count' => 1,
    ]);
});
