<?php

namespace App\Models;

use App\Events\SyncRunFinished;
use App\Services\Ingestion\FailureReason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * @property int $id
 * @property string $status
 * @property CarbonImmutable $started_at
 * @property CarbonImmutable|null $finished_at
 * @property int $submissions_listed
 * @property int $submissions_upserted
 * @property int $review_assignments_upserted
 * @property int $failures_api_error
 * @property int $failures_validation
 * @property int $failures_db
 * @property int $failures_other
 * @property string|null $error_excerpt
 */
class SyncRun extends Model
{
    public const RUNNING = 'running';

    public const SUCCESS = 'success';

    public const FAILURE = 'failure';

    public $timestamps = false;

    // Millisecond precision so durations of sub-second runs are not rounded to zero.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
        ];
    }

    public static function start(): self
    {
        return self::create(['status' => self::RUNNING, 'started_at' => CarbonImmutable::now()]);
    }

    public function failureCount(): int
    {
        return $this->failures_api_error + $this->failures_validation + $this->failures_db + $this->failures_other;
    }

    public function durationSeconds(): ?float
    {
        return $this->finished_at === null
            ? null
            : ($this->finished_at->getTimestampMs() - $this->started_at->getTimestampMs()) / 1000;
    }

    /** Counts a failure under its reason and keeps the first error message for the UI. */
    public function recordFailure(Throwable $e): void
    {
        self::query()->whereKey($this->id)->increment('failures_'.FailureReason::classify($e));
        self::query()->whereKey($this->id)->whereNull('error_excerpt')
            ->update(['error_excerpt' => mb_substr($e->getMessage(), 0, 500)]);
        $this->refresh();
    }

    /** @param  int  $failedJobs  jobs the queue batch saw fail, even if no reason was recorded */
    public function finish(int $failedJobs = 0): void
    {
        $this->refresh();
        $this->update([
            'status' => $this->failureCount() > 0 || $failedJobs > 0 ? self::FAILURE : self::SUCCESS,
            'finished_at' => CarbonImmutable::now(),
        ]);

        SyncRunFinished::dispatch($this);
    }
}
