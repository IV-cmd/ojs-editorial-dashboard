<?php

namespace App\Services\Dashboard;

use App\Models\ReviewAssignment;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The editorial rules behind the dashboard: what counts as stuck and what counts as overdue.
 *
 * Every rule takes the reference time as an argument instead of reading the clock, so the same
 * data always gives the same answer no matter when it runs. Methods return query builders or
 * plain arrays; paging and JSON shaping belong to the HTTP layer.
 */
final class EditorialQueries
{
    // OJS 3.6 submission status codes, read from pkp-lib source and not verified against a live server.
    public const STATUS_QUEUED = 1;

    public const STATUS_PUBLISHED = 3;

    public const STATUS_DECLINED = 4;

    public const STATUS_SCHEDULED = 5;

    public function __construct(private readonly int $stuckDays) {}

    public function stuckDays(): int
    {
        return $this->stuckDays;
    }

    /**
     * @return Builder<Submission>
     */
    public function submissions(?int $status = null, ?int $contextId = null): Builder
    {
        return Submission::query()
            ->withCount('reviewAssignments')
            ->when($status !== null, fn (Builder $query) => $query->where('status', $status))
            ->when($contextId !== null, fn (Builder $query) => $query->where('context_id', $contextId))
            ->orderBy('id');
    }

    /**
     * Stuck: still in the workflow (status queued) with no recorded activity for at least
     * `stuckDays` days. Idle for exactly the threshold counts as stuck. A submission with no
     * activity date is left out, since there is nothing to measure the idle time from.
     *
     * @return Builder<Submission>
     */
    public function stuckSubmissions(CarbonImmutable $asOf): Builder
    {
        return Submission::query()
            ->withCount('reviewAssignments')
            ->where('status', self::STATUS_QUEUED)
            ->where('date_last_activity', '<=', $asOf->subDays($this->stuckDays))
            ->orderBy('date_last_activity')
            ->orderBy('id');
    }

    /**
     * Overdue: due before the day of `$asOf`, not completed, and still live (not declined or
     * cancelled). A review due today is not overdue until the day ends. A review completed after
     * its due date is not overdue either, because it is done.
     *
     * @return Builder<ReviewAssignment>
     */
    public function overdueReviews(CarbonImmutable $asOf): Builder
    {
        return ReviewAssignment::query()
            ->with('submission')
            ->where('date_due', '<', $asOf->startOfDay())
            ->whereNull('date_completed')
            ->where('declined', false)
            ->where('cancelled', false)
            ->orderBy('date_due')
            ->orderBy('id');
    }

    /**
     * The distinct filter values the data actually contains, so the UI can build its dropdowns
     * from real options instead of a hardcoded list that could drift from the data.
     *
     * @return array{statuses: list<int>, context_ids: list<int>}
     */
    public function filterOptions(): array
    {
        return [
            'statuses' => Submission::query()->distinct()->orderBy('status')->pluck('status')->map(fn ($v): int => (int) $v)->all(),
            'context_ids' => Submission::query()->distinct()->orderBy('context_id')->pluck('context_id')->map(fn ($v): int => (int) $v)->all(),
        ];
    }

    /**
     * @return array{submissions_total: int, submissions_by_status: list<array{status: int, count: int}>, stuck_count: int, overdue_reviews_count: int}
     */
    public function summary(CarbonImmutable $asOf): array
    {
        $byStatus = Submission::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->toBase()
            ->get()
            ->map(fn (object $row): array => [
                'status' => (int) $row->status,
                'count' => (int) $row->aggregate,
            ])
            ->values()
            ->all();

        return [
            'submissions_total' => array_sum(array_column($byStatus, 'count')),
            'submissions_by_status' => $byStatus,
            'stuck_count' => $this->stuckSubmissions($asOf)->count(),
            'overdue_reviews_count' => $this->overdueReviews($asOf)->count(),
        ];
    }
}
