<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewAssignmentResource;
use App\Http\Resources\SubmissionResource;
use App\Services\Dashboard\EditorialQueries;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Reading the clock happens here, at the edge; the rules in EditorialQueries receive it as input.
class DashboardController extends Controller
{
    public function __construct(private readonly EditorialQueries $queries) {}

    public function summary(): JsonResponse
    {
        $asOf = CarbonImmutable::now();

        return response()->json([
            'data' => $this->queries->summary($asOf),
            'meta' => ['as_of' => $asOf->toIso8601String(), 'stuck_threshold_days' => $this->queries->stuckDays()],
        ]);
    }

    public function stuck(Request $request): AnonymousResourceCollection
    {
        $asOf = CarbonImmutable::now();

        return SubmissionResource::collection(
            $this->queries->stuckSubmissions($asOf)->paginate($this->perPage($request))->withQueryString(),
        )->additional(['meta' => ['as_of' => $asOf->toIso8601String(), 'stuck_threshold_days' => $this->queries->stuckDays()]]);
    }

    public function overdueReviews(Request $request): AnonymousResourceCollection
    {
        $asOf = CarbonImmutable::now();

        return ReviewAssignmentResource::collection(
            $this->queries->overdueReviews($asOf)->paginate($this->perPage($request))->withQueryString(),
        )->additional(['meta' => ['as_of' => $asOf->toIso8601String()]]);
    }

    private function perPage(Request $request): int
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('dashboard.max_per_page')],
        ]);

        return (int) ($validated['per_page'] ?? config('dashboard.per_page'));
    }
}
