<?php

use App\Events\SyncRunFinished;
use App\Events\SyncRunStarted;
use App\Models\Submission;
use App\Models\SyncRun;
use App\Services\Ojs\OjsApiException;
use App\Services\Ojs\OjsClientInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('records a successful run with counts and emits start and finish events', function () {
    Event::fake([SyncRunStarted::class, SyncRunFinished::class]);

    Artisan::call('ojs:sync');

    $run = SyncRun::sole();
    expect($run->status)->toBe(SyncRun::SUCCESS)
        ->and($run->submissions_listed)->toBe(5)
        ->and($run->submissions_upserted)->toBe(5)
        ->and($run->review_assignments_upserted)->toBe(3)
        ->and($run->failureCount())->toBe(0)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->durationSeconds())->toBeGreaterThanOrEqual(0.0);

    Event::assertDispatched(SyncRunStarted::class);
    Event::assertDispatched(SyncRunFinished::class, fn (SyncRunFinished $e) => $e->run->is($run) && $e->run->status === SyncRun::SUCCESS);
});

it('records a failed run when listing submissions fails', function () {
    app()->instance(OjsClientInterface::class, failingOjsClient(failListing: true));

    expect(fn () => Artisan::call('ojs:sync'))->toThrow(OjsApiException::class);

    $run = SyncRun::sole();
    expect($run->status)->toBe(SyncRun::FAILURE)
        ->and($run->failures_api_error)->toBe(1)
        ->and($run->finished_at)->not->toBeNull()
        ->and($run->error_excerpt)->toContain('upstream unavailable');
});

it('records a failed run when one submission fails, keeping the others', function () {
    // Per-submission failure accounting needs a real queue: retries, then failed(), then the
    // batch closing the run. Drive it with the database queue and a worker, skipping backoff.
    config(['queue.default' => 'database']);
    app()->instance(OjsClientInterface::class, failingOjsClient(failDetailId: 103));
    Event::fake([SyncRunFinished::class]);

    Artisan::call('ojs:sync');
    for ($i = 0; $i < 5 && (SyncRun::first()->status ?? SyncRun::RUNNING) === SyncRun::RUNNING; $i++) {
        Artisan::call('queue:work', ['--stop-when-empty' => true]);
        $this->travel(61)->seconds();
    }

    $run = SyncRun::sole();
    expect($run->status)->toBe(SyncRun::FAILURE)
        ->and($run->submissions_listed)->toBe(5)
        ->and($run->submissions_upserted)->toBe(4)
        ->and($run->failures_api_error)->toBe(1)
        ->and($run->error_excerpt)->toContain('detail exploded')
        ->and(Submission::where('ojs_id', 103)->exists())->toBeFalse()
        ->and(Submission::count())->toBe(4);

    Event::assertDispatchedTimes(SyncRunFinished::class, 1);
});
