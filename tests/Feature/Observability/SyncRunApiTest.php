<?php

use App\Models\SyncRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function failedRun(): SyncRun
{
    return SyncRun::create([
        'status' => SyncRun::FAILURE,
        'started_at' => CarbonImmutable::parse('2026-03-15 12:00:00'),
        'finished_at' => CarbonImmutable::parse('2026-03-15 12:00:02'),
        'submissions_listed' => 5,
        'submissions_upserted' => 4,
        'failures_api_error' => 1,
        'error_excerpt' => 'OJS API request failed with HTTP status 500: upstream body',
    ]);
}

it('requires a token', function () {
    $this->getJson('/api/sync-runs/latest')->assertUnauthorized();
});

it('returns null data before any sync has run', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/sync-runs/latest')->assertOk()->assertExactJson(['data' => null]);
});

it('returns the latest run with its counts', function () {
    Sanctum::actingAs(User::factory()->create());
    Artisan::call('ojs:sync');
    Artisan::call('ojs:sync');

    $this->getJson('/api/sync-runs/latest')->assertOk()
        ->assertJsonPath('data.id', SyncRun::max('id'))
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.submissions_upserted', 5)
        ->assertJsonPath('data.review_assignments_upserted', 3)
        ->assertJsonPath('data.failures.api_error', 0);
});

it('shows the error excerpt to editors only', function () {
    failedRun();

    Sanctum::actingAs(User::factory()->editor()->create());
    $this->getJson('/api/sync-runs/latest')->assertOk()
        ->assertJsonPath('data.status', 'failure')
        ->assertJsonPath('data.duration_seconds', 2)
        ->assertJsonPath('data.error_excerpt', 'OJS API request failed with HTTP status 500: upstream body');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/sync-runs/latest')->assertOk()
        ->assertJsonPath('data.failures.api_error', 1)
        ->assertJsonMissingPath('data.error_excerpt');
});
