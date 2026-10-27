<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\SubmissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user()?->only(['id', 'name', 'email', 'role']));

    // Distinct status/context values present in the data, for the UI's filter dropdowns.
    Route::get('/meta/filters', [MetaController::class, 'filters']);

    // Raw submission records, including reviewer IDs on assignments: editors only.
    Route::middleware('can:view-submissions')->group(function () {
        Route::get('/submissions', [SubmissionController::class, 'index']);
        Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);
    });

    // Dashboard views: every authenticated role, with reviewer identity filtered per role.
    Route::prefix('dashboard')->controller(DashboardController::class)->group(function () {
        Route::get('/summary', 'summary');
        Route::get('/stuck', 'stuck');
        Route::get('/overdue-reviews', 'overdueReviews');
    });
});
